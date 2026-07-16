<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Brukerrettede portal-URL-er (uten «v3» i path).
 * Interne klassenavn/view-mapper kan fortsatt hete PortalV3* / portal-v3/.
 */
final class PortalPaths
{
    public const LEGACY_PREFIX = '/portal-v3';

    public static function oversikt(): string
    {
        return '/oversikt';
    }

    public static function login(): string
    {
        return '/login';
    }

    public static function logout(): string
    {
        return '/logout';
    }

    public static function cups(): string
    {
        return '/cups';
    }

    public static function cup(): string
    {
        return '/cup';
    }

    public static function cupEdit(): string
    {
        return '/cup/rediger';
    }

    public static function sesonger(): string
    {
        return '/sesonger';
    }

    public static function sesongNew(): string
    {
        return '/sesonger/ny';
    }

    public static function sesongEdit(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/rediger';
    }

    public static function sesongStruktur(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/struktur';
    }

    public static function sesongSammenlagt(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/sammenlagt';
    }

    public static function sesongArchive(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/arkiver';
    }

    public static function sesongCupStandings(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/cup-standings';
    }

    public static function sesongChildNew(int $parentId): string
    {
        return '/sesonger/' . $parentId . '/undersoner/ny';
    }

    public static function sesongChildren(int $parentId): string
    {
        return '/sesonger/' . $parentId . '/undersoner';
    }

    public static function sesongStevner(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/stevner';
    }

    public static function sesongStevneNew(int $seriesId): string
    {
        return '/sesonger/' . $seriesId . '/stevner/ny';
    }

    public static function arrangorer(): string
    {
        return '/arrangorer';
    }

    public static function arrangor(int $orgId): string
    {
        return '/arrangorer/' . $orgId;
    }

    public static function arrangorNyttStevne(): string
    {
        return '/arrangorer/nytt-stevne';
    }

    public static function stevner(): string
    {
        return '/stevner';
    }

    public static function stevne(int $eventId): string
    {
        return '/stevner/' . $eventId;
    }

    public static function stevneArchive(int $eventId): string
    {
        return '/stevner/' . $eventId . '/arkiver';
    }

    public static function kontekstOrganisasjon(): string
    {
        return '/kontekst/organisasjon';
    }

    public static function kontekstOrganisasjonBytt(): string
    {
        return '/kontekst/organisasjon/bytt';
    }

    public static function kontekstSesong(): string
    {
        return '/kontekst/sesong';
    }

    public static function kontekstArbeidsomrade(): string
    {
        return '/kontekst/arbeidsomrade';
    }

    /** Alias for eldre kode som forventet «prefix + path». */
    public static function routePrefix(): string
    {
        return '';
    }

    public static function isPortalPath(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }
        if (str_starts_with($path, self::LEGACY_PREFIX)) {
            return true;
        }

        $roots = [
            '/oversikt', '/login', '/logout', '/cups', '/cup', '/sesonger',
            '/arrangorer', '/stevner', '/kontekst',
        ];
        foreach ($roots as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }
}
