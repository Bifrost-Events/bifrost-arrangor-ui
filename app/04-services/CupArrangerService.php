<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Arrangører i aktiv cup — organisasjoner som eier stevner i cupen
 * (ikke global org-liste).
 */
final class CupArrangerService
{
    public function __construct(
        private readonly PortalV3Services $services,
        private readonly CupSeasonResolver $seasons = new CupSeasonResolver(),
    ) {
    }

    /**
     * @return array{
     *   season: array<string, mixed>|null,
     *   season_series_ids: list<int>,
     *   arrangers: list<array{
     *     org_id: int,
     *     name: string,
     *     event_count: int,
     *     season_event_count: int,
     *     events: list<array<string, mixed>>,
     *     missing_season_event: bool
     *   }>
     * }
     */
    public function listForSpace(int $personId, array $space, int $orgId, ?int $seasonSeriesId = null): array
    {
        $spaceId = (int) ($space['space_id'] ?? 0);
        $hierarchy = $this->services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $roots = $hierarchy['roots'] ?? [];
        $children = $hierarchy['children'] ?? [];
        $season = $this->seasons->resolveActiveRoot($roots, $seasonSeriesId);
        $seasonIds = $season !== null ? $this->seasons->collectSeriesIds($season, $children) : [];

        $events = $this->services->events->listForSpace($personId, $spaceId, $orgId);
        $byOrg = [];
        foreach ($events as $event) {
            $oid = (int) ($event['owner_org_id'] ?? 0);
            if ($oid <= 0) {
                continue;
            }
            if (!isset($byOrg[$oid])) {
                $byOrg[$oid] = [
                    'org_id' => $oid,
                    'name' => (string) ($event['owner_org_name'] ?? ('Organisasjon #' . $oid)),
                    'event_count' => 0,
                    'season_event_count' => 0,
                    'events' => [],
                    'season_events' => [],
                    'seasons' => [],
                    'missing_season_event' => true,
                ];
            }
            $byOrg[$oid]['event_count']++;
            $byOrg[$oid]['events'][] = $event;
            $seriesId = (int) ($event['series_id'] ?? 0);
            if ($seasonIds === [] || in_array($seriesId, $seasonIds, true)) {
                $byOrg[$oid]['season_event_count']++;
                $byOrg[$oid]['season_events'][] = $event;
                $byOrg[$oid]['missing_season_event'] = false;
            }
        }

        // Også inkludér hosts fra DB-aggregering (i tilfelle API-filtrering skjulte noen)
        foreach ($this->services->spaceParticipation->listHostOrganizationsInSpace($spaceId) as $host) {
            $oid = (int) ($host['org_id'] ?? 0);
            if ($oid <= 0 || isset($byOrg[$oid])) {
                continue;
            }
            $byOrg[$oid] = [
                'org_id' => $oid,
                'name' => (string) ($host['name'] ?? ''),
                'event_count' => (int) ($host['event_count'] ?? 0),
                'season_event_count' => 0,
                'events' => [],
                'season_events' => [],
                'seasons' => [],
                'missing_season_event' => true,
            ];
        }

        if ($seasonIds !== []) {
            foreach ($this->services->spaceParticipation->listHostOrganizationsInSpace($spaceId, $seasonIds) as $host) {
                $oid = (int) ($host['org_id'] ?? 0);
                if ($oid <= 0) {
                    continue;
                }
                if (!isset($byOrg[$oid])) {
                    $byOrg[$oid] = [
                        'org_id' => $oid,
                        'name' => (string) ($host['name'] ?? ''),
                        'event_count' => 0,
                        'season_event_count' => (int) ($host['event_count'] ?? 0),
                        'events' => [],
                        'season_events' => [],
                        'seasons' => [],
                        'missing_season_event' => ((int) ($host['event_count'] ?? 0)) === 0,
                    ];
                } else {
                    $byOrg[$oid]['season_event_count'] = max(
                        $byOrg[$oid]['season_event_count'],
                        (int) ($host['event_count'] ?? 0),
                    );
                    $byOrg[$oid]['missing_season_event'] = $byOrg[$oid]['season_event_count'] === 0;
                }
            }
        }

        $seasonBySeries = $this->seasons->seasonLabelsBySeriesId($roots, $children);
        foreach ($byOrg as &$row) {
            $names = [];
            foreach ($row['events'] as $event) {
                $sid = (int) ($event['series_id'] ?? 0);
                $label = trim((string) ($seasonBySeries[$sid] ?? ''));
                if ($label !== '') {
                    $names[$label] = true;
                }
            }
            // Fallback via DB dersom API ikke returnerte events for denne org.
            if ($names === [] && $spaceId > 0) {
                foreach ($this->services->spaceParticipation->listOwnerSeriesInSpace($spaceId) as $pair) {
                    if ((int) ($pair['org_id'] ?? 0) !== (int) $row['org_id']) {
                        continue;
                    }
                    $label = trim((string) ($seasonBySeries[(int) ($pair['series_id'] ?? 0)] ?? ''));
                    if ($label !== '') {
                        $names[$label] = true;
                    }
                }
            }
            $seasonList = array_keys($names);
            sort($seasonList, SORT_STRING);
            $row['seasons'] = $seasonList;
        }
        unset($row);

        $list = array_values($byOrg);
        usort($list, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'season' => $season,
            'season_series_ids' => $seasonIds,
            'arrangers' => $list,
        ];
    }
}
