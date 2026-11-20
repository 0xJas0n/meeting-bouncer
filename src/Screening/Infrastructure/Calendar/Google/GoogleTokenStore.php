<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Calendar\Google;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the OAuth tokens of the single demo user in a local file (var/, gitignored, chmod 0600).
 * It holds a refresh token: treat it like a password. Multi-user would need an encrypted DB column per user.
 */
final readonly class GoogleTokenStore
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/google_token.json')] private string $path)
    {
    }

    /** @return array{refresh_token: string, access_token: string, expires_at: int}|null */
    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!\is_array($data) || !isset($data['refresh_token'], $data['access_token'], $data['expires_at'])) {
            return null;
        }

        return ['refresh_token' => (string) $data['refresh_token'], 'access_token' => (string) $data['access_token'], 'expires_at' => (int) $data['expires_at']];
    }

    /** @param array{refresh_token: string, access_token: string, expires_at: int} $tokens */
    public function save(array $tokens): void
    {
        $dir = \dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $tmp = $this->path.'.tmp';
        file_put_contents($tmp, json_encode($tokens, \JSON_THROW_ON_ERROR));
        chmod($tmp, 0600);
        rename($tmp, $this->path);
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
