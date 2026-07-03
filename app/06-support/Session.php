<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const AUTH_KEY = 'bifrost_arrangor_auth';
    private const ORG_KEY = 'bifrost_arrangor_org_id';
    private const SEASON_KEY = 'bifrost_arrangor_season_id';
    private const BACKEND_COOKIE_KEY = 'bifrost_backend_cookie';
    private const FLASH_KEY = 'bifrost_arrangor_flash';

    /** @var bool|null */
    private static ?bool $configured = null;

    public static function startRequired(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configureCookieParams();
        session_name('BIFROSTARRANGOR');
        session_start();
    }

    /** @param array<string, mixed> $user */
    public static function setAuth(array $user): void
    {
        self::startRequired();
        $_SESSION[self::AUTH_KEY] = $user;
    }

    /** @return array<string, mixed>|null */
    public static function getAuth(): ?array
    {
        self::startRequired();
        $auth = $_SESSION[self::AUTH_KEY] ?? null;

        return is_array($auth) ? $auth : null;
    }

    public static function clear(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        unset(
            $_SESSION[self::AUTH_KEY],
            $_SESSION[self::ORG_KEY],
            $_SESSION[self::SEASON_KEY],
            $_SESSION[self::BACKEND_COOKIE_KEY],
            $_SESSION[self::FLASH_KEY],
        );
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $p['path'] ?? '/',
                'domain' => $p['domain'] ?? '',
                'secure' => (bool) ($p['secure'] ?? false),
                'httponly' => (bool) ($p['httponly'] ?? true),
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function setSelectedOrganizationId(?int $organizationId): void
    {
        self::startRequired();
        if ($organizationId === null || $organizationId <= 0) {
            unset($_SESSION[self::ORG_KEY]);
            return;
        }
        $_SESSION[self::ORG_KEY] = $organizationId;
    }

    public static function getSelectedOrganizationId(): ?int
    {
        self::startRequired();
        $id = $_SESSION[self::ORG_KEY] ?? null;
        if ($id === null) {
            return null;
        }

        return (int) $id > 0 ? (int) $id : null;
    }

    public static function setSelectedSeasonId(?int $seasonId): void
    {
        self::startRequired();
        if ($seasonId === null || $seasonId <= 0) {
            unset($_SESSION[self::SEASON_KEY]);
            return;
        }
        $_SESSION[self::SEASON_KEY] = $seasonId;
    }

    public static function getSelectedSeasonId(): ?int
    {
        self::startRequired();
        $id = $_SESSION[self::SEASON_KEY] ?? null;
        if ($id === null) {
            return null;
        }

        return (int) $id > 0 ? (int) $id : null;
    }

    public static function setBackendCookie(string $cookie): void
    {
        self::startRequired();
        $_SESSION[self::BACKEND_COOKIE_KEY] = $cookie;
    }

    public static function getBackendCookie(): string
    {
        self::startRequired();
        $cookie = $_SESSION[self::BACKEND_COOKIE_KEY] ?? '';

        return is_string($cookie) ? $cookie : '';
    }

    public static function clearBackendCookie(): void
    {
        self::startRequired();
        unset($_SESSION[self::BACKEND_COOKIE_KEY]);
    }

    /** @param array<string, string> $errors */
    public static function setFlash(string $type, string $message, array $errors = []): void
    {
        self::startRequired();
        $_SESSION[self::FLASH_KEY] = [
            'type' => $type,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /** @return array{type: string, message: string, errors: array<string, string>}|null */
    public static function pullFlash(): ?array
    {
        self::startRequired();
        $flash = $_SESSION[self::FLASH_KEY] ?? null;
        unset($_SESSION[self::FLASH_KEY]);
        if (!is_array($flash)) {
            return null;
        }

        return [
            'type' => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
            'errors' => is_array($flash['errors'] ?? null) ? $flash['errors'] : [],
        ];
    }

    private static function configureCookieParams(): void
    {
        if (self::$configured === true) {
            return;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $domain = '';
        if (str_ends_with($host, '.bifrost.local') || $host === 'bifrost.local') {
            $domain = '.bifrost.local';
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        self::$configured = true;
    }
}
