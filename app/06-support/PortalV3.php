<?php

declare(strict_types=1);

namespace App\Support;

final class PortalV3
{
    public static function isEnabled(): bool
    {
        return Config::get('v3.enabled', false) === true;
    }

    /**
     * Brukerrettet portal har ikke lenger et «v3»-prefix (se PortalPaths).
     * Beholdes for bakoverkompatibilitet; returnerer alltid tom streng.
     */
    public static function routePrefix(): string
    {
        return PortalPaths::routePrefix();
    }

    public static function authBypassEnabled(): bool
    {
        return Config::get('v3.auth_bypass', false) === true;
    }
}
