<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\PortalBoundCup;
use App\Service\PortalV3Services;

/** Henter aktiv cup (space) fra session/domene for ruter uten spaceId i URL. */
final class PortalActiveSpace
{
    /**
     * @return array{0: int, 1: null}|array{0: null, 1: array{status: int, headers: array<string, string>, body: string}}
     */
    public static function requireId(PortalV3Services $services): array
    {
        $personId = PortalV3Auth::personId() ?? 0;
        $bound = (new PortalBoundCup($services))->resolve($personId);
        $spaceId = (int) (($bound['space']['space_id'] ?? 0));
        if ($spaceId <= 0) {
            return [null, Response::redirect(PortalPaths::cups())];
        }

        return [$spaceId, null];
    }
}
