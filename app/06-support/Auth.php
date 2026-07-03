<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\AuthService;

final class Auth
{
    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return Session::getAuth();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hasOrganizerAccess(): bool
    {
        $user = self::user();

        return $user !== null && AuthService::canAccessOrganizer($user);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function requireLogin(): ?array
    {
        if (!self::check()) {
            return Response::redirect('/login');
        }

        if (Session::getBackendCookie() === '') {
            Session::clear();
            Session::setFlash('error', 'Backend-sesjon mangler — logg inn på nytt.');

            return Response::redirect('/login');
        }

        return null;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function requireOrganizer(): ?array
    {
        if ($redirect = self::requireLogin()) {
            return $redirect;
        }

        if (!self::hasOrganizerAccess()) {
            return Response::redirect('/bli-arrangor');
        }

        return null;
    }
}
