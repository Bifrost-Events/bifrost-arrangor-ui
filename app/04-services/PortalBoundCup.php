<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\PortalV3Session;

/** Sikrer én aktiv cup + valgt sesong for domenet / session. */
final class PortalBoundCup
{
    public function __construct(
        private readonly PortalV3Services $services,
        private readonly CupSeasonResolver $seasons = new CupSeasonResolver(),
    ) {
    }

    /**
     * @return array{
     *   space: array<string, mixed>|null,
     *   domain_bound: bool,
     *   season: array<string, mixed>|null,
     *   season_label: string,
     *   season_series_id: int|null,
     *   season_options: list<array{series_id: int, label: string}>,
     *   season_series_ids: list<int>,
     *   access: array<string, mixed>,
     *   domain: array<string, mixed>|null
     * }
     */
    public function resolve(int $personId): array
    {
        $domain = $this->services->domainContext->resolveFromRequest();
        $domainBound = $domain !== null;
        $space = null;

        $sessionSpaceId = $this->services->organizationContext->activeSpaceId();
        if ($sessionSpaceId !== null && $sessionSpaceId > 0) {
            $space = $this->services->eventSpaces->findAccessibleForPerson($personId, $sessionSpaceId);
        }

        if ($space === null && $domainBound) {
            $spaces = $this->services->eventSpaces->listAdministrable($personId);
            if (count($spaces) >= 1) {
                $space = $spaces[0];
                $sid = (int) ($space['space_id'] ?? 0);
                if ($sid > 0) {
                    PortalV3Session::setSpaceId($sid);
                    $space = $this->services->eventSpaces->findAccessibleForPerson($personId, $sid) ?? $space;
                }
            }
        }

        $access = $space !== null
            ? (new PortalCupAccess($this->services))->forSpace($personId, $space)
            : [
                'is_cup_admin' => false,
                'is_arranger_admin' => false,
                'can_manage_cup' => false,
                'can_view_arrangers' => false,
                'can_view_all_events' => false,
                'admin_org_ids' => [],
                'arranger_org_ids' => [],
            ];

        $season = null;
        $seasonLabel = '';
        $seasonSeriesId = null;
        $seasonOptions = [];
        $seasonSeriesIds = [];

        if ($space !== null) {
            $orgId = (int) ($this->services->organizationContext->activeOrganizationId()
                ?? $space['owner_org_id']
                ?? 0);
            if ($orgId > 0) {
                $hierarchy = $this->services->series->hierarchyForSpace(
                    $personId,
                    (int) $space['space_id'],
                    $orgId,
                );
                $roots = $hierarchy['roots'] ?? [];
                $children = $hierarchy['children'] ?? [];
                foreach ($roots as $root) {
                    $rid = (int) ($root['series_id'] ?? 0);
                    if ($rid <= 0) {
                        continue;
                    }
                    $label = trim((string) ($root['name'] ?? $root['season_label'] ?? ('Sesong #' . $rid)));
                    $seasonOptions[] = ['series_id' => $rid, 'label' => $label];
                }

                $preferred = PortalV3Session::getSeasonSeriesId();
                $season = $this->seasons->resolveActiveRoot($roots, $preferred);
                if ($season !== null) {
                    $seasonSeriesId = (int) ($season['series_id'] ?? 0) ?: null;
                    $seasonLabel = trim((string) ($season['name'] ?? $season['season_label'] ?? ''));
                    $seasonSeriesIds = $this->seasons->collectSeriesIds($season, $children);
                    // Persist auto-valg så UI og filtre er konsistente.
                    if ($seasonSeriesId !== null && $preferred !== $seasonSeriesId) {
                        PortalV3Session::setSeasonSeriesId($seasonSeriesId);
                    }
                } elseif ($preferred !== null) {
                    // Ugyldig lagret sesong for denne cupen
                    PortalV3Session::setSeasonSeriesId(null);
                }
            }
        }

        return [
            'space' => $space,
            'domain_bound' => $domainBound,
            'season' => $season,
            'season_label' => $seasonLabel,
            'season_series_id' => $seasonSeriesId,
            'season_options' => $seasonOptions,
            'season_series_ids' => $seasonSeriesIds,
            'access' => $access,
            'domain' => $domain,
        ];
    }
}
