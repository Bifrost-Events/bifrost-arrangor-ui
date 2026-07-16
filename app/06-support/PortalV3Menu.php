<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\PortalEventTerminology;

final class PortalV3Menu
{
    /**
     * @param array<string, mixed> $access PortalCupAccess::forSpace
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function build(
        PortalEventTerminology $labels,
        ?array $activeSpace,
        string $currentPath,
        bool $domainBound,
        array $access,
    ): array {
        $items = [];
        $spaceId = $activeSpace !== null ? (int) ($activeSpace['space_id'] ?? 0) : 0;

        if ($spaceId > 0) {
            if ($access['can_manage_cup'] ?? false) {
                $items[] = [
                    'label' => 'Oversikt',
                    'href' => PortalPaths::oversikt(),
                    'active' => rtrim($currentPath, '/') === '/oversikt' || rtrim($currentPath, '/') === '',
                ];
                $items[] = [
                    'label' => 'Cupadministrasjon',
                    'href' => PortalPaths::cup(),
                    'active' => str_starts_with($currentPath, '/cup')
                        || (str_starts_with($currentPath, '/sesonger')
                            && !str_contains($currentPath, '/stevner')),
                ];
                $items[] = [
                    'label' => 'Arrangører',
                    'href' => PortalPaths::arrangorer(),
                    'active' => str_starts_with($currentPath, '/arrangorer'),
                ];
            }

            $items[] = [
                'label' => $labels->plural('event'),
                'href' => PortalPaths::stevner() . (($access['can_manage_cup'] ?? false) ? '' : '?season_scope=all'),
                'active' => $currentPath === '/stevner'
                    || str_starts_with($currentPath, '/stevner/')
                    || str_contains($currentPath, '/stevner'),
            ];
        } elseif (!$domainBound) {
            $items[] = [
                'label' => 'Alle ' . strtolower($labels->plural('event_space')),
                'href' => PortalPaths::cups(),
                'active' => rtrim($currentPath, '/') === '/cups',
            ];
        }

        return $items;
    }
}
