<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Screening\Application\Import\CalendarSources;
use App\Screening\Application\Import\InviteFactory;
use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Privacy\Pseudonymizer;
use App\Screening\Infrastructure\Calendar\Google\GoogleAuthorization;
use App\Screening\Infrastructure\Calendar\Google\GoogleCalendarSource;
use App\Screening\Infrastructure\Calendar\Google\GoogleTokenStore;
use App\Shared\Domain\Clock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleCalendarSourceTest extends TestCase
{
    private string $tokenFile;
    /** @var list<string> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->tokenFile = sys_get_temp_dir().'/gcal_token_'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenFile);
    }

    private function clock(): Clock
    {
        return new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-04 08:00:00');
            }
        };
    }

    private function auth(MockHttpClient $http, int $expiresAt = 4102444800): GoogleAuthorization
    {
        $store = new GoogleTokenStore($this->tokenFile);
        $store->save(['refresh_token' => 'r', 'access_token' => 'a', 'expires_at' => $expiresAt]);

        return new GoogleAuthorization($http, $store, $this->clock(), 'client-id', 'client-secret');
    }

    /** @param list<array<string, mixed>> $items */
    private function source(array $items, string $seriesStart = '2026-02-02T09:30:00+01:00'): GoogleCalendarSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($items, $seriesStart): MockResponse {
            $this->requests[] = $url;
            $body = str_contains($url, '/events/series-1') ? ['start' => ['dateTime' => $seriesStart]] : ['items' => $items];

            return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type: application/json']]);
        });

        return new GoogleCalendarSource($http, $this->auth($http), $this->clock());
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        $me = ['email' => 'demo.user@example.com', 'self' => true, 'responseStatus' => 'accepted'];

        return [
            ['id' => 'ev1', 'summary' => 'Roadmap with Erika Mustermann', 'description' => '<p>Please mail erika.mustermann@example.com or call +43 660 1234567</p>',
                'start' => ['dateTime' => '2026-11-05T10:00:00+01:00'], 'end' => ['dateTime' => '2026-11-05T11:30:00+01:00'],
                'attendees' => [$me + ['optional' => true], ['email' => 'erika.mustermann@example.com', 'displayName' => 'Erika Mustermann'], ['email' => 'room-4@resource.example.com', 'resource' => true]]],
            ['id' => 'ev-declined', 'summary' => 'Declined', 'start' => ['dateTime' => '2026-11-05T12:00:00+01:00'], 'end' => ['dateTime' => '2026-11-05T13:00:00+01:00'],
                'attendees' => [['email' => 'demo.user@example.com', 'self' => true, 'responseStatus' => 'declined']]],
            ['id' => 'ev-allday', 'summary' => 'Holiday', 'start' => ['date' => '2026-11-06'], 'end' => ['date' => '2026-11-07']],
            ['id' => 'ev-cancelled', 'status' => 'cancelled', 'start' => ['dateTime' => '2026-11-05T14:00:00+01:00'], 'end' => ['dateTime' => '2026-11-05T15:00:00+01:00']],
            ['id' => 'ev-ooo', 'eventType' => 'outOfOffice', 'summary' => 'OOO', 'start' => ['dateTime' => '2026-11-06T09:00:00+01:00'], 'end' => ['dateTime' => '2026-11-06T17:00:00+01:00']],
            ['id' => 'ev-focus', 'eventType' => 'focusTime', 'summary' => 'Focus', 'start' => ['dateTime' => '2026-11-05T14:00:00+01:00'], 'end' => ['dateTime' => '2026-11-05T16:00:00+01:00']],
            ['id' => 'ev-weekly', 'summary' => 'Team Status', 'recurringEventId' => 'series-1', 'start' => ['dateTime' => '2026-11-05T09:30:00+01:00'], 'end' => ['dateTime' => '2026-11-05T10:00:00+01:00']],
            ['id' => 'ev-de', 'summary' => 'Besprechung', 'description' => 'Bitte die Zahlen für den Termin mit', 'start' => ['dateTime' => '2026-11-05T16:00:00+01:00'], 'end' => ['dateTime' => '2026-11-05T16:30:00+01:00'], 'attendees' => [$me]],
        ];
    }

    public function testMapsEventsAndSkipsWhatIsNotAMeeting(): void
    {
        $invites = iterator_to_array($this->source($this->items())->invites(), false);
        $byId = array_column(array_map(static fn ($i) => ['id' => $i->id, 'i' => $i], $invites), 'i', 'id');

        self::assertSame(['gcal-ev1', 'gcal-ev-weekly', 'gcal-ev-de'], array_keys($byId), 'declined, all-day, cancelled, out-of-office and focus time are not invites');
        $roadmap = $byId['gcal-ev1'];
        self::assertSame(90, $roadmap->durationMinutes);
        self::assertCount(2, $roadmap->attendees, 'meeting room is not a guest');
        self::assertTrue($roadmap->attendees[0]->optional);
        self::assertSame(Language::DE, $byId['gcal-ev-de']->language);
        self::assertSame(Language::EN, $roadmap->language);
        self::assertCount(1, $byId['gcal-ev-weekly']->attendees, 'own event without guest list still counts the user');
        self::assertNotNull($byId['gcal-ev-weekly']->recurringSince, 'series start looked up on the master event');
    }

    public function testFocusTimeBecomesFocusBlock(): void
    {
        $blocks = iterator_to_array($this->source($this->items())->focusBlocks(), false);

        self::assertCount(1, $blocks);
        self::assertSame(120, $blocks[0]->durationMinutes);
    }

    public function testIsReadOnlyAndFetchesOnce(): void
    {
        $source = $this->source($this->items());
        iterator_to_array($source->invites(), false);
        iterator_to_array($source->focusBlocks(), false);

        $listCalls = array_filter($this->requests, static fn (string $u): bool => str_contains($u, 'singleEvents=true'));
        self::assertCount(1, $listCalls, 'one list call serves invites and focus blocks');
        self::assertStringContainsString('fields=', $this->requests[0], 'only needed fields are requested');
    }

    /** Raw Google data goes through the same ACL as fixtures: nothing personal survives. */
    public function testImportedInvitesArePseudonymized(): void
    {
        $raw = iterator_to_array($this->source($this->items())->invites(), false)[0];
        $invite = (new InviteFactory(new Pseudonymizer()))->create($raw);
        $stored = $invite->title().' '.$invite->description()->value.' '.implode(' ', $invite->attendeeLabels() === [] ? [] : array_map('strval', $invite->attendeeLabels()));

        foreach (['Erika', 'Mustermann', '@', '1234567', 'example.com'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $stored);
        }
        self::assertStringContainsString('Person A', $stored);
    }

    public function testAuthorizationUrlRequestsReadOnlyOfflineAccess(): void
    {
        $url = $this->auth(new MockHttpClient())->authorizationUrl('http://localhost/cb', 'st4te');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $q);

        self::assertSame('https://www.googleapis.com/auth/calendar.events.readonly', $q['scope']);
        self::assertSame('offline', $q['access_type']);
        self::assertSame('st4te', $q['state']);
    }

    public function testExpiredAccessTokenIsRefreshed(): void
    {
        $http = new MockHttpClient(new MockResponse('{"access_token":"fresh","expires_in":3600}', ['response_headers' => ['content-type: application/json']]));
        $auth = $this->auth($http, 1);

        self::assertSame('fresh', $auth->accessToken());
        self::assertSame('r', (new GoogleTokenStore($this->tokenFile))->load()['refresh_token'] ?? null, 'refresh token is kept');
    }

    public function testRevokedGrantClearsTheStoredToken(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400, 'response_headers' => ['content-type: application/json']]));
        $auth = $this->auth($http, 1);

        try {
            $auth->accessToken();
            self::fail('expected failure');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Connect again', $e->getMessage());
        }
        self::assertFalse($auth->isConnected());
    }

    public function testCodeExchangeWithoutRefreshTokenFails(): void
    {
        $http = new MockHttpClient(new MockResponse('{"access_token":"a","expires_in":3600}', ['response_headers' => ['content-type: application/json']]));

        $this->expectException(\RuntimeException::class);
        $this->auth($http)->complete('code', 'http://localhost/cb');
    }

    public function testUnknownSourceNameListsTheAvailableOnes(): void
    {
        $sources = new CalendarSources([$this->source([])]);

        $this->expectExceptionMessage('Available: google');
        $sources->get('outlook');
    }

    public function testTokenFileIsPrivate(): void
    {
        $this->auth(new MockHttpClient());

        self::assertSame('0600', substr(sprintf('%o', fileperms($this->tokenFile)), -4));
    }
}
