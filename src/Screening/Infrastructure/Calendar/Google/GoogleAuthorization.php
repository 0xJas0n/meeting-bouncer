<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Calendar\Google;

use App\Screening\Application\Port\CalendarConnection;
use App\Shared\Domain\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Minimal OAuth 2.0 web flow. Read-only scope: events can be read, never created, changed or deleted. */
final readonly class GoogleAuthorization implements CalendarConnection
{
    public const SCOPE = 'https://www.googleapis.com/auth/calendar.events.readonly';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private HttpClientInterface $http,
        private GoogleTokenStore $tokens,
        private Clock $clock,
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function isConnected(): bool
    {
        return null !== $this->tokens->load();
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent', // makes Google return a refresh token every time
            'state' => $state,
        ]);
    }

    public function complete(string $code, string $redirectUri): void
    {
        $data = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
        if (!isset($data['refresh_token'])) {
            throw new \RuntimeException('Google returned no refresh token. Revoke the app at myaccount.google.com/permissions and connect again.');
        }
        $this->tokens->save([
            'refresh_token' => (string) $data['refresh_token'],
            'access_token' => (string) $data['access_token'],
            'expires_at' => $this->clock->now()->getTimestamp() + (int) $data['expires_in'],
        ]);
    }

    public function disconnect(): void
    {
        $this->tokens->clear();
    }

    /** A valid access token, refreshed when it is about to expire. */
    public function accessToken(): string
    {
        $stored = $this->tokens->load() ?? throw new \RuntimeException('Google Calendar is not connected.');
        if ($stored['expires_at'] > $this->clock->now()->getTimestamp() + 60) {
            return $stored['access_token'];
        }

        try {
            $data = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $stored['refresh_token']]);
        } catch (\RuntimeException $e) {
            $this->tokens->clear(); // revoked or expired grant: force a clean reconnect
            throw new \RuntimeException('Google access was revoked or expired. Connect again.', 0, $e);
        }
        $this->tokens->save([
            'refresh_token' => (string) ($data['refresh_token'] ?? $stored['refresh_token']),
            'access_token' => (string) $data['access_token'],
            'expires_at' => $this->clock->now()->getTimestamp() + (int) $data['expires_in'],
        ]);

        return (string) $data['access_token'];
    }

    /**
     * @param array<string, string> $params
     *
     * @return array<string, mixed>
     */
    private function tokenRequest(array $params): array
    {
        $response = $this->http->request('POST', self::TOKEN_URL, [
            'body' => $params + ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret],
        ]);
        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            // only the OAuth error code, never the body (may echo credentials)
            throw new \RuntimeException(sprintf('Google token request failed: %s', \is_string($data['error'] ?? null) ? $data['error'] : 'unknown error'));
        }

        return $data;
    }
}
