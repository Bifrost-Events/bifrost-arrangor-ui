<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\PortalEventTerminology;

final class PortalV3Menu
{
    /**
     * Primærfaner for aktiv arbeidsmodus (cup vs stevnearrangør).
     * Onboarding/konto ligger i brukermenyen — ikke her.
     *
     * @param array<string, mixed> $access PortalCupAccess::forSpace / menuAccess
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function build(
        PortalEventTerminology $labels,
        ?array $activeSpace,
        string $currentPath,
        bool $domainBound,
        array $access,
        int $seasonSeriesId = 0,
    ): array {
        $items = [];
        $spaceId = $activeSpace !== null ? (int) ($activeSpace['space_id'] ?? 0) : 0;
        $canManageCup = (bool) ($access['can_manage_cup'] ?? false);

        if ($spaceId > 0) {
            if ($canManageCup) {
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
                            && !str_contains($currentPath, '/stevner')
                            && !str_contains($currentPath, '/arrangor-soknader')),
                ];
                $items[] = [
                    'label' => 'Arrangører',
                    'href' => PortalPaths::arrangorer(),
                    'active' => str_starts_with($currentPath, '/arrangorer'),
                ];
            }

            $items[] = [
                'label' => $labels->plural('event'),
                'href' => PortalPaths::stevner() . ($canManageCup ? '' : '?season_scope=all'),
                'active' => $currentPath === '/stevner'
                    || str_starts_with($currentPath, '/stevner/')
                    || str_contains($currentPath, '/stevner'),
            ];

            if ($canManageCup && $seasonSeriesId > 0) {
                $items[] = [
                    'label' => 'Søknader om arrangør',
                    'href' => PortalPaths::serieSoknader($seasonSeriesId),
                    'active' => str_contains($currentPath, '/arrangor-soknader')
                        && str_starts_with($currentPath, '/sesonger/'),
                ];
            }
        } elseif (!$domainBound) {
            $items[] = [
                'label' => 'Alle ' . strtolower($labels->plural('event_space')),
                'href' => PortalPaths::cups(),
                'active' => rtrim($currentPath, '/') === '/cups',
            ];
        }

        return $items;
    }

    /**
     * Konto-/onboarding-lenker til brukermenyen.
     *
     * @return list<array{label: string, href: string}>
     */
    public static function accountLinks(): array
    {
        return [
            ['label' => 'Kom i gang', 'href' => PortalPaths::komIGang()],
            ['label' => 'Mine organisasjoner', 'href' => PortalPaths::mineOrganisasjoner()],
            ['label' => 'Arrangørsøknader', 'href' => PortalPaths::arrangorSoknader()],
        ];
    }
}
