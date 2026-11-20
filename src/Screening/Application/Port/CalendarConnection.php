<?php

declare(strict_types=1);

namespace App\Screening\Application\Port;

/** Authorizing access to an external calendar (OAuth). Read-only access is requested. */
interface CalendarConnection
{
    /** Credentials (client id/secret) are configured. */
    public function isConfigured(): bool;

    /** The user granted access and a token is stored. */
    public function isConnected(): bool;

    public function authorizationUrl(string $redirectUri, string $state): string;

    public function complete(string $code, string $redirectUri): void;

    /** Forget the stored token. */
    public function disconnect(): void;
}
