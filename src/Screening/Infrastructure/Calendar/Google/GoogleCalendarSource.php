<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Calendar\Google;

use App\Screening\Application\Import\RawAttendee;
use App\Screening\Application\Import\RawFocusBlock;
use App\Screening\Application\Import\RawInvite;
use App\Screening\Application\Port\CalendarSource;
use App\Screening\Domain\Model\Language;
use App\Shared\Domain\Clock;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read-only import of the primary calendar for the next days. Raw attendee data lives only in
 * memory here; InviteFactory pseudonymizes it before anything is stored or sent anywhere.
 *
 * Data minimization: only the fields we need are requested, descriptions are cut to 2000 characters,
 * all-day events, declined events, out-of-office and rooms are skipped.
 */
final class GoogleCalendarSource implements CalendarSource
{
    private const API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    private const FIELDS = 'nextPageToken,items(id,status,organizer(email,self),eventType,summary,description,start,end,recurringEventId,attendees(email,displayName,self,optional,resource,responseStatus))';
    private const MAX_DESCRIPTION = 2000;
    private const SKIPPED_TYPES = ['outOfOffice', 'workingLocation', 'birthday'];

    /** @var list<array<string, mixed>>|null */
    private ?array $events = null;
    /** @var array<string, \DateTimeImmutable|null> */
    private array $seriesStarts = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly GoogleAuthorization $auth,
        private readonly Clock $clock,
        private readonly int $days = 7,
    ) {
    }

    public function name(): string
    {
        return 'google';
    }

    public function invites(): iterable
    {
        foreach ($this->events() as $e) {
            if ('focusTime' === ($e['eventType'] ?? 'default') || null === ($invite = $this->toInvite($e))) {
                continue;
            }
            yield $invite;
        }
    }

    public function focusBlocks(): iterable
    {
        foreach ($this->events() as $e) {
            if ('focusTime' !== ($e['eventType'] ?? null) || 'cancelled' === ($e['status'] ?? null) || null === ($start = $this->dateTime($e['start'] ?? null)) || null === ($end = $this->dateTime($e['end'] ?? null))) {
                continue;
            }
            yield new RawFocusBlock('gcal-'.$e['id'], $start, max(1, (int) (($end->getTimestamp() - $start->getTimestamp()) / 60)));
        }
    }

    /** @param array<string, mixed> $e */
    private function toInvite(array $e): ?RawInvite
    {
        if ('cancelled' === ($e['status'] ?? null) || \in_array($e['eventType'] ?? 'default', self::SKIPPED_TYPES, true)) {
            return null;
        }
        $start = $this->dateTime($e['start'] ?? null);
        $end = $this->dateTime($e['end'] ?? null);
        if (null === $start || null === $end) { // all-day events have `date`, not `dateTime`
            return null;
        }

        $attendees = [];
        $selfPresent = false;
        foreach ((array) ($e['attendees'] ?? []) as $a) {
            if (true === ($a['resource'] ?? false)) { // meeting rooms are no guests
                continue;
            }
            $self = true === ($a['self'] ?? false);
            if ($self && 'declined' === ($a['responseStatus'] ?? null)) {
                return null;
            }
            $selfPresent = $selfPresent || $self;
            $attendees[] = new RawAttendee((string) ($a['displayName'] ?? ''), (string) ($a['email'] ?? ''), $self, true === ($a['optional'] ?? false));
        }
        if (!$selfPresent) { // own event without guest list
            $attendees[] = new RawAttendee('', '', true);
        }

        $title = (string) ($e['summary'] ?? '') ?: '(no title)';
        $description = $this->plainText((string) ($e['description'] ?? ''));

        return new RawInvite(
            'gcal-'.$e['id'],
            $title,
            $description,
            $start,
            max(1, (int) (($end->getTimestamp() - $start->getTimestamp()) / 60)),
            $attendees,
            isset($e['recurringEventId']) ? $this->seriesStart((string) $e['recurringEventId']) : null,
            $this->guessLanguage($title.' '.$description),
            $this->organizerEmail($e),
        );
    }

    /** @param array<string, mixed> $e */
    private function organizerEmail(array $e): ?string
    {
        $o = (array) ($e['organizer'] ?? []);
        $mail = (string) ($o['email'] ?? '');
        // own events, group calendars and rooms have nobody to answer
        if ('' === $mail || true === ($o['self'] ?? false) || str_ends_with($mail, '@group.calendar.google.com') || str_ends_with($mail, '@resource.calendar.google.com')) {
            return null;
        }

        return $mail;
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        if (null !== $this->events) {
            return $this->events;
        }
        $from = $this->clock->now()->setTime(0, 0);
        $events = [];
        $pageToken = null;
        do {
            $query = [
                'timeMin' => $from->format(\DATE_RFC3339),
                'timeMax' => $from->modify(sprintf('+%d days', $this->days))->format(\DATE_RFC3339),
                'singleEvents' => 'true', // expand recurring series into instances
                'orderBy' => 'startTime',
                'maxResults' => 250,
                'fields' => self::FIELDS,
            ];
            if (null !== $pageToken) {
                $query['pageToken'] = $pageToken;
            }
            $page = $this->get(self::API, $query);
            $events = array_merge($events, array_values((array) ($page['items'] ?? [])));
            $pageToken = isset($page['nextPageToken']) ? (string) $page['nextPageToken'] : null;
        } while (null !== $pageToken);

        return $this->events = $events;
    }

    /** Start of the recurring series (for the zombie check). Unknown on any error. */
    private function seriesStart(string $recurringEventId): ?\DateTimeImmutable
    {
        if (!\array_key_exists($recurringEventId, $this->seriesStarts)) {
            try {
                $master = $this->get(self::API.'/'.rawurlencode($recurringEventId), ['fields' => 'start']);
                $s = (array) ($master['start'] ?? []);
                $this->seriesStarts[$recurringEventId] = $this->dateTime($s) ?? (isset($s['date']) ? new \DateTimeImmutable((string) $s['date']) : null);
            } catch (ExceptionInterface) {
                $this->seriesStarts[$recurringEventId] = null;
            }
        }

        return $this->seriesStarts[$recurringEventId];
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $url, array $query): array
    {
        return $this->http->request('GET', $url, ['query' => $query, 'auth_bearer' => $this->auth->accessToken()])->toArray();
    }

    private function dateTime(mixed $field): ?\DateTimeImmutable
    {
        if (!\is_array($field) || !isset($field['dateTime'])) {
            return null;
        }

        // keep the instant, show it in the app's timezone
        return (new \DateTimeImmutable((string) $field['dateTime']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $html)), \ENT_QUOTES | \ENT_HTML5);
        $text = trim((string) preg_replace('/[ \t]+|\n{3,}/u', ' ', $text));

        return mb_substr($text, 0, self::MAX_DESCRIPTION);
    }

    /** Cheap heuristic: two German stop words and it is German. Good enough to pick the reply language. */
    private function guessLanguage(string $text): Language
    {
        return preg_match_all('/\b(und|der|die|das|bitte|nicht|für|mit|wir|ist|besprechung|termin)\b/iu', $text) >= 2 ? Language::DE : Language::EN;
    }
}
