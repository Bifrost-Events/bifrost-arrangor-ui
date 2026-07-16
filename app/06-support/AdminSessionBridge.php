<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Synkroniserer V3-innlogging til delt BIFROSTADMIN-session for API-kall mot bifrost-events.
 */
final class AdminSessionBridge
{
    private const SESSION_NAME = 'BIFROSTADMIN';
    private const AUTH_KEY = 'bifrost_admin_auth';

    /** @param array<string, mixed> $user */
    public static function syncFromPortalUser(array $user): void
    {
        $previousName = session_name();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;
        if ($wasActive) {
            session_write_close();
        }

        self::configureCookieParams();
        session_name(self::SESSION_NAME);
        session_start();
        $_SESSION[self::AUTH_KEY] = [
            'user_id' => (int) ($user['user_id'] ?? 0),
            'person_id' => (int) ($user['person_id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
        ];
        unset($_SESSION['bifrost_admin_logged_out']);
        session_write_close();

        if ($wasActive) {
            session_name($previousName);
            Session::startRequired();
        }
    }

    public static function clear(): void
    {
        $previousName = session_name();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;
        if ($wasActive) {
            session_write_close();
        }

        self::configureCookieParams();
        session_name(self::SESSION_NAME);
        session_start();
        unset($_SESSION[self::AUTH_KEY]);
        $_SESSION['bifrost_admin_logged_out'] = true;
        session_write_close();

        if ($wasActive) {
            session_name($previousName);
            Session::startRequired();
        }
    }

    private static function configureCookieParams(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
