<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\PortalV3Services;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3SeriesController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createRootForm(int $spaceId): array
    {
        return $this->form($spaceId, null, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createChildForm(int $spaceId, int $parentSeriesId): array
    {
        return $this->form($spaceId, $parentSeriesId, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function editForm(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null || !$services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            PortalV3Session::setFlash('error', 'Serie ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        $spaceId = (int) ($series['space_id'] ?? 0);

        return $this->form($spaceId, null, $series);
    }

    /**
     * @param array<string, mixed>|null $series
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function form(int $spaceId, ?int $parentSeriesId, ?array $series): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Event Space ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $parent = null;
        if ($parentSeriesId !== null) {
            $parent = $services->series->findAccessible($personId, $parentSeriesId, $orgId);
            if ($parent === null || !$services->seriesPolicy->canCreateChildSeries($personId, $parent, $orgId)) {
                PortalV3Session::setFlash('error', 'Ingen tilgang til å opprette underserie.');

                return Response::redirect(PortalPaths::cup());
            }
            if ($block = $this->blockUnlessRoundsStructure($parent)) {
                return $block;
            }
        } elseif ($series === null && !$services->seriesPolicy->canCreate($personId, $orgId, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til å opprette serie.');

            return Response::redirect(PortalPaths::cup());
        }

        PortalV3Session::setSpaceId($spaceId);
        $labels = $services->labels->resolveForSpace($space);
        $isEdit = $series !== null;
        $isChild = $parentSeriesId !== null || (int) ($series['parent_series_id'] ?? 0) > 0;

        $cupStandings = null;
        $cupEventChoices = [];
        $showSeasonTabs = false;
        if ($isEdit && !$isChild && $series !== null) {
            $showSeasonTabs = true;
        }

        return PortalV3View::render('series/form', [
            'space' => $space,
            'series' => $series,
            'parent' => $parent,
            'labels' => $labels,
            'is_edit' => $isEdit,
            'is_child' => $isChild,
            'show_season_tabs' => $showSeasonTabs,
            'show_cup_standings' => false,
            'cup_event_choices' => $cupEventChoices,
        ], ($isEdit ? 'Rediger ' : 'Ny ') . ($isChild ? $labels->singular('subseries') : $labels->singular('series')));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function structureForm(int $seriesId): array
    {
        return $this->seasonTab($seriesId, 'structure');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function scoringForm(int $seriesId): array
    {
        return $this->seasonTab($seriesId, 'scoring');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function seasonTab(int $seriesId, string $tab): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null || !$services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            PortalV3Session::setFlash('error', 'Serie ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }
        if ((int) ($series['parent_series_id'] ?? 0) > 0) {
            PortalV3Session::setFlash('error', 'Struktur og sammenlagt gjelder kun sesong.');

            return Response::redirect(PortalPaths::sesongEdit($seriesId));
        }

        $spaceId = (int) ($series['space_id'] ?? 0);
        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Event Space ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $bundle = $services->series->getSeasonStructureScoring($personId, $orgId, $seriesId);
        if ($bundle === null) {
            PortalV3Session::setFlash('error', 'Kunne ikke hente struktur/sammenlagt.');

            return Response::redirect(PortalPaths::sesongEdit($seriesId));
        }

        PortalV3Session::setSpaceId($spaceId);
        $labels = $services->labels->resolveForSpace($space);
        $series = is_array($bundle['series'] ?? null) && $bundle['series'] !== []
            ? $bundle['series']
            : $series;

        $view = $tab === 'scoring' ? 'series/scoring' : 'series/structure';
        $title = ($tab === 'scoring' ? 'Sammenlagtregler — ' : 'Struktur — ')
            . (string) ($series['name'] ?? $labels->singular('series'));

        return PortalV3View::render($view, [
            'space' => $space,
            'series' => $series,
            'labels' => $labels,
            'structure_bundle' => $bundle,
        ], $title);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function structureSubmit(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null || !$services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        $result = $services->series->updateSeasonStructure($personId, $orgId, $seriesId, [
            'structure_type' => (string) ($_POST['structure_type'] ?? ''),
        ]);
        if (!($result['ok'] ?? false)) {
            $err = $result['error'] ?? 'Kunne ikke lagre struktur.';
            if (!empty($result['errors']) && is_array($result['errors'])) {
                $err = (string) (reset($result['errors']) ?: $err);
            }
            PortalV3Session::setFlash('error', $err);

            return Response::redirect(PortalPaths::sesongStruktur($seriesId));
        }

        PortalV3Session::setFlash('success', 'Sesongstruktur lagret.');

        return Response::redirect(PortalPaths::cup());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function scoringSubmit(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null || !$services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        $payload = [
            'selection_count' => (int) ($_POST['selection_count'] ?? 0),
            'minimum_participation' => ($_POST['minimum_participation'] ?? '') === ''
                ? 0
                : (int) $_POST['minimum_participation'],
            'value_source' => (string) ($_POST['value_source'] ?? 'raw_score'),
            'placement_points' => is_array($_POST['placement_points'] ?? null) ? $_POST['placement_points'] : [],
        ];

        $result = $services->series->updateSeasonScoringConfig($personId, $orgId, $seriesId, $payload);
        if (!($result['ok'] ?? false)) {
            $err = $result['error'] ?? 'Kunne ikke lagre sammenlagtregler.';
            if (!empty($result['errors']) && is_array($result['errors'])) {
                $err = (string) (reset($result['errors']) ?: $err);
            }
            PortalV3Session::setFlash('error', $err);

            return Response::redirect(PortalPaths::sesongSammenlagt($seriesId));
        }

        PortalV3Session::setFlash('success', 'Sammenlagtregler lagret.');

        return Response::redirect(PortalPaths::sesongSammenlagt($seriesId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createRootSubmit(int $spaceId): array
    {
        return $this->save($spaceId, null, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createChildSubmit(int $spaceId, int $parentSeriesId): array
    {
        return $this->save($spaceId, $parentSeriesId, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function editSubmit(int $seriesId): array
    {
        return $this->save(0, null, $seriesId);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function archiveSubmit(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null) {
            PortalV3Session::setFlash('error', 'Serie ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $spaceId = (int) ($series['space_id'] ?? 0);
        if (!$services->series->archive($personId, $orgId, $seriesId, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke arkivere serie.');

            return Response::redirect(PortalPaths::cup());
        }

        PortalV3Session::setFlash('success', 'Serie arkivert.');

        return Response::redirect(PortalPaths::cup());
    }

    private function save(int $spaceId, ?int $parentSeriesId, ?int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);

        if ($seriesId !== null) {
            $existing = $services->series->findAccessible($personId, $seriesId, $orgId);
            if ($existing === null) {
                PortalV3Session::setFlash('error', 'Serie ikke funnet.');

                return Response::redirect(PortalPaths::cups());
            }
            $spaceId = (int) ($existing['space_id'] ?? 0);
            $parentSeriesId = (int) ($existing['parent_series_id'] ?? 0) ?: null;
        }

        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Event Space ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $data = [
            'owner_org_id' => $orgId,
            'space_id' => $spaceId,
            'parent_series_id' => $parentSeriesId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'series_type' => (string) ($_POST['series_type'] ?? ($parentSeriesId !== null ? 'round' : 'season')),
            'season_label' => trim((string) ($_POST['season_label'] ?? '')) ?: null,
            'sort_order' => ($_POST['sort_order'] ?? '') !== '' ? (int) $_POST['sort_order'] : null,
            'starts_at' => $this->normalizeSeriesDateBound(trim((string) ($_POST['starts_on'] ?? '')), false),
            'ends_at' => $this->normalizeSeriesDateBound(trim((string) ($_POST['ends_on'] ?? '')), true),
            'status' => (string) ($_POST['status'] ?? 'active'),
            'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
        ];

        if ($data['name'] === '') {
            PortalV3Session::setFlash('error', 'Navn er påkrevd.');

            return $seriesId !== null
                ? Response::redirect(PortalPaths::sesongEdit($seriesId))
                : Response::redirect(PortalPaths::cup());
        }

        $startsOn = $this->toDateOnly($data['starts_at']);
        $endsOn = $this->toDateOnly($data['ends_at']);
        if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
            PortalV3Session::setFlash('error', 'Til-dato kan ikke være før fra-dato.');

            return $seriesId !== null
                ? Response::redirect(PortalPaths::sesongEdit($seriesId))
                : ($parentSeriesId !== null
                    ? Response::redirect(PortalPaths::sesongChildNew($parentSeriesId))
                    : Response::redirect(PortalPaths::sesongNew()));
        }

        if ($seriesId === null) {
            if ($parentSeriesId !== null) {
                $parent = $services->series->findAccessible($personId, $parentSeriesId, $orgId);
                if ($parent === null) {
                    PortalV3Session::setFlash('error', 'Foreldreserie ikke funnet.');

                    return Response::redirect(PortalPaths::cup());
                }
                if ($block = $this->blockUnlessRoundsStructure($parent)) {
                    return $block;
                }
            }

            $newId = $services->series->create($personId, $orgId, $data, $userId > 0 ? $userId : null);
            if ($newId === null) {
                PortalV3Session::setFlash('error', 'Kunne ikke opprette serie.');

                return Response::redirect(PortalPaths::cup());
            }
            PortalV3Session::setFlash('success', 'Serie opprettet.');

            // Ny sesong: gå til strukturvalg først. Ny runde: tilbake til cup-oversikt.
            if ($parentSeriesId === null) {
                return Response::redirect(PortalPaths::sesongStruktur($newId));
            }

            return Response::redirect(PortalPaths::cup());
        }

        if (!$services->series->update($personId, $orgId, $seriesId, $data, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke lagre serie.');

            return Response::redirect(PortalPaths::sesongEdit($seriesId));
        }

        PortalV3Session::setFlash('success', 'Serie lagret.');

        return Response::redirect(PortalPaths::sesongEdit($seriesId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function cupStandingsSubmit(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null || !$services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        $placementRaw = $_POST['placement_points'] ?? [];
        $placementPoints = is_array($placementRaw) ? $placementRaw : [];

        $submittedIds = $_POST['cup_event_ids'] ?? null;
        $allChecked = !empty($_POST['cup_events_all']);
        if ($allChecked || $submittedIds === null) {
            $eventIds = null;
        } else {
            $eventIds = is_array($submittedIds) ? array_map('intval', $submittedIds) : [];
        }

        $payload = [
            'cup_standings_mode' => (string) ($_POST['cup_standings_mode'] ?? 'total_score'),
            'cup_standings_count_best' => (int) ($_POST['cup_standings_count_best'] ?? 6),
            'cup_placement_points' => $placementPoints,
            'cup_standings_event_ids' => $eventIds,
        ];

        $result = $services->series->updateCupStandings($personId, $orgId, $seriesId, $payload);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Kunne ikke lagre sammenlagt-innstillinger.');

            return Response::redirect(PortalPaths::sesongEdit($seriesId));
        }

        PortalV3Session::setFlash('success', 'Sammenlagt-innstillinger lagret.');

        return Response::redirect(PortalPaths::sesongEdit($seriesId));
    }

    /** Batch-rediger sesongperiode + runder fra cup-oversikten. */
    public function roundsMatrixSubmit(int $seasonRootId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $resolved = $this->resolveSeriesAccess($services, $personId, $seasonRootId);
        if ($resolved === null) {
            PortalV3Session::setFlash('error', 'Sesong ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cup());
        }
        $orgId = $resolved['org_id'];
        $season = $resolved['series'];
        if (!$services->seriesPolicy->canEdit($personId, $season, $orgId)) {
            PortalV3Session::setFlash('error', 'Sesong ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cup());
        }
        if ((int) ($season['parent_series_id'] ?? 0) > 0) {
            PortalV3Session::setFlash('error', 'Rundematrisen gjelder sesongen, ikke en enkelt runde.');

            return Response::redirect(PortalPaths::cup());
        }

        $spaceId = (int) ($season['space_id'] ?? 0);
        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $existingRounds = $hierarchy['children'][$seasonRootId] ?? [];
        if ($existingRounds === []) {
            $existingRounds = $services->spaceParticipation->listChildSeriesByParentId($seasonRootId);
        }
        $roundById = [];
        foreach ($existingRounds as $round) {
            $rid = (int) ($round['series_id'] ?? 0);
            if ($rid > 0) {
                $roundById[$rid] = $round;
            }
        }

        $seasonPost = is_array($_POST['season'] ?? null) ? $_POST['season'] : [];
        $seasonStartsOn = trim((string) ($seasonPost['starts_on'] ?? ''));
        $seasonEndsOn = trim((string) ($seasonPost['ends_on'] ?? ''));
        $seasonStartsAt = $this->normalizeSeriesDateBound($seasonStartsOn, false);
        $seasonEndsAt = $this->normalizeSeriesDateBound($seasonEndsOn, true);
        $seasonFrom = $this->toDateOnly($seasonStartsAt);
        $seasonTo = $this->toDateOnly($seasonEndsAt);

        $errors = [];
        if ($seasonFrom !== null && $seasonTo !== null && $seasonTo < $seasonFrom) {
            $errors[] = 'Sesong: til-dato kan ikke være før fra-dato.';
        }

        $postedRounds = is_array($_POST['rounds'] ?? null) ? $_POST['rounds'] : [];
        $parsedRounds = [];
        foreach ($postedRounds as $ridRaw => $row) {
            $rid = (int) $ridRaw;
            if ($rid <= 0 || !isset($roundById[$rid]) || !is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $fromOn = trim((string) ($row['starts_on'] ?? ''));
            $toOn = trim((string) ($row['ends_on'] ?? ''));
            $sortOrder = ($row['sort_order'] ?? '') !== '' ? (int) $row['sort_order'] : null;
            $from = $this->toDateOnly($fromOn);
            $to = $this->toDateOnly($toOn);
            $label = $name !== '' ? $name : (string) ($roundById[$rid]['name'] ?? ('Runde #' . $rid));

            if ($name === '') {
                $errors[] = $label . ': navn er påkrevd.';
            }
            if ($from !== null && $to !== null && $to < $from) {
                $errors[] = $label . ': til-dato kan ikke være før fra-dato.';
            }
            if ($from !== null && $seasonFrom !== null && $from < $seasonFrom) {
                $errors[] = $label . ': fra-dato er før sesongens start (' . $seasonFrom . ').';
            }
            if ($from !== null && $seasonTo !== null && $from > $seasonTo) {
                $errors[] = $label . ': fra-dato er etter sesongens slutt (' . $seasonTo . ').';
            }
            if ($to !== null && $seasonFrom !== null && $to < $seasonFrom) {
                $errors[] = $label . ': til-dato er før sesongens start (' . $seasonFrom . ').';
            }
            if ($to !== null && $seasonTo !== null && $to > $seasonTo) {
                $errors[] = $label . ': til-dato er etter sesongens slutt (' . $seasonTo . ').';
            }

            $parsedRounds[] = [
                'series_id' => $rid,
                'existing' => $roundById[$rid],
                'name' => $name,
                'label' => $label,
                'starts_on' => $from,
                'ends_on' => $to,
                'starts_at' => $this->normalizeSeriesDateBound($fromOn, false),
                'ends_at' => $this->normalizeSeriesDateBound($toOn, true),
                'sort_order' => $sortOrder,
            ];
        }

        // Overlapp mellom runder (når begge har intervall)
        $dated = array_values(array_filter(
            $parsedRounds,
            static fn (array $r): bool => ($r['starts_on'] ?? null) !== null && ($r['ends_on'] ?? null) !== null,
        ));
        usort($dated, static function (array $a, array $b): int {
            $cmp = strcmp((string) $a['starts_on'], (string) $b['starts_on']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) $a['series_id']) <=> ((int) $b['series_id']);
        });
        for ($i = 0, $n = count($dated); $i < $n - 1; ++$i) {
            $a = $dated[$i];
            $b = $dated[$i + 1];
            if ((string) $a['ends_on'] >= (string) $b['starts_on']) {
                $errors[] = $a['label'] . ' og ' . $b['label'] . ': datointervallene overlapper.';
            }
        }

        if ($errors !== []) {
            PortalV3Session::setFlash('error', implode(' ', $errors));

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $seasonFull = $services->series->findAccessible($personId, $seasonRootId, $orgId) ?? $season;
        $seasonData = [
            'owner_org_id' => (int) ($seasonFull['owner_org_id'] ?? $orgId),
            'space_id' => $spaceId,
            'parent_series_id' => null,
            'name' => (string) ($seasonFull['name'] ?? ''),
            'short_name' => $seasonFull['short_name'] ?? null,
            'slug' => $seasonFull['slug'] ?? null,
            'description' => $seasonFull['description'] ?? null,
            'series_type' => (string) ($seasonFull['series_type'] ?? 'season'),
            'season_label' => $seasonFull['season_label'] ?? null,
            'sort_order' => isset($seasonFull['sort_order']) ? (int) $seasonFull['sort_order'] : null,
            'starts_at' => $seasonStartsAt,
            'ends_at' => $seasonEndsAt,
            'status' => (string) ($seasonFull['status'] ?? 'active'),
            'visibility' => (string) ($seasonFull['visibility'] ?? 'internal'),
        ];
        if (!$services->series->update($personId, $orgId, $seasonRootId, $seasonData, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke lagre sesongperioden.');

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $failed = [];
        foreach ($parsedRounds as $row) {
            $full = $services->series->findAccessible($personId, (int) $row['series_id'], $orgId);
            if ($full === null) {
                $failed[] = $row['label'];
                continue;
            }
            $data = [
                'owner_org_id' => (int) ($full['owner_org_id'] ?? $orgId),
                'space_id' => $spaceId,
                'parent_series_id' => $seasonRootId,
                'name' => $row['name'],
                'short_name' => $full['short_name'] ?? null,
                'slug' => $full['slug'] ?? null,
                'description' => $full['description'] ?? null,
                'series_type' => (string) ($full['series_type'] ?? 'round'),
                'season_label' => $full['season_label'] ?? null,
                'sort_order' => $row['sort_order'] ?? (isset($full['sort_order']) ? (int) $full['sort_order'] : null),
                'starts_at' => $row['starts_at'],
                'ends_at' => $row['ends_at'],
                'status' => (string) ($full['status'] ?? 'active'),
                'visibility' => (string) ($full['visibility'] ?? 'internal'),
            ];
            if (!$services->series->update($personId, $orgId, (int) $row['series_id'], $data, $userId > 0 ? $userId : null)) {
                $failed[] = $row['label'];
            }
        }

        if ($failed !== []) {
            PortalV3Session::setFlash('error', 'Sesong lagret, men noen runder feilet: ' . implode(', ', $failed));
        } else {
            PortalV3Session::setFlash('success', 'Sesong og runder lagret.');
        }

        return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
    }

    /** Opprett N runder under sesong (tom rundestruktur). */
    public function roundsBatchCreate(int $seasonRootId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $resolved = $this->resolveSeriesAccess($services, $personId, $seasonRootId);
        if ($resolved === null) {
            PortalV3Session::setFlash('error', 'Sesong ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cup());
        }
        $orgId = $resolved['org_id'];
        $season = $resolved['series'];
        if (!$services->seriesPolicy->canEdit($personId, $season, $orgId)) {
            PortalV3Session::setFlash('error', 'Sesong ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cup());
        }
        if ((int) ($season['parent_series_id'] ?? 0) > 0) {
            PortalV3Session::setFlash('error', 'Runder opprettes under sesongen, ikke under en enkelt runde.');

            return Response::redirect(PortalPaths::cup());
        }
        if ($blocked = $this->blockUnlessRoundsStructure($season)) {
            return $blocked;
        }

        $spaceId = (int) ($season['space_id'] ?? 0);
        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $existingRounds = $hierarchy['children'][$seasonRootId] ?? [];
        if ($existingRounds === []) {
            $existingRounds = $services->spaceParticipation->listChildSeriesByParentId($seasonRootId);
        }
        if ($existingRounds !== []) {
            PortalV3Session::setFlash(
                'error',
                'Sesongen har allerede runder. Bruk «Ny runde» for å legge til én, eller rediger eksisterende.',
            );

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $count = (int) ($_POST['round_count'] ?? 0);
        if ($count < 1 || $count > 24) {
            PortalV3Session::setFlash('error', 'Velg antall runder mellom 1 og 24.');

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $seasonPost = is_array($_POST['season'] ?? null) ? $_POST['season'] : [];
        $seasonStartsOn = trim((string) ($seasonPost['starts_on'] ?? ''));
        $seasonEndsOn = trim((string) ($seasonPost['ends_on'] ?? ''));
        if ($seasonStartsOn === '') {
            $seasonStartsOn = (string) ($this->toDateOnly($season['starts_at'] ?? null) ?? '');
        }
        if ($seasonEndsOn === '') {
            $seasonEndsOn = (string) ($this->toDateOnly($season['ends_at'] ?? null) ?? '');
        }
        $seasonStartsAt = $this->normalizeSeriesDateBound($seasonStartsOn, false);
        $seasonEndsAt = $this->normalizeSeriesDateBound($seasonEndsOn, true);
        $seasonFrom = $this->toDateOnly($seasonStartsAt);
        $seasonTo = $this->toDateOnly($seasonEndsAt);

        if ($seasonFrom === null || $seasonTo === null) {
            PortalV3Session::setFlash('error', 'Sett sesong fra/til før du oppretter runder.');

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }
        if ($seasonTo < $seasonFrom) {
            PortalV3Session::setFlash('error', 'Sesong: til-dato kan ikke være før fra-dato.');

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $seasonFull = $services->series->findAccessible($personId, $seasonRootId, $orgId) ?? $season;
        $ownerOrgId = (int) ($seasonFull['owner_org_id'] ?? $orgId);
        $seasonData = [
            'owner_org_id' => $ownerOrgId,
            'space_id' => $spaceId,
            'parent_series_id' => null,
            'name' => (string) ($seasonFull['name'] ?? ''),
            'short_name' => $seasonFull['short_name'] ?? null,
            'slug' => $seasonFull['slug'] ?? null,
            'description' => $seasonFull['description'] ?? null,
            'series_type' => (string) ($seasonFull['series_type'] ?? 'season'),
            'season_label' => $seasonFull['season_label'] ?? null,
            'sort_order' => isset($seasonFull['sort_order']) ? (int) $seasonFull['sort_order'] : null,
            'starts_at' => $seasonStartsAt,
            'ends_at' => $seasonEndsAt,
            'status' => (string) ($seasonFull['status'] ?? 'active'),
            'visibility' => (string) ($seasonFull['visibility'] ?? 'internal'),
        ];
        if (!$services->series->update($personId, $orgId, $seasonRootId, $seasonData, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke lagre sesongperioden.');

            return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
        }

        $labels = $services->labels->resolveBySpaceId($spaceId, $orgId);
        $roundLabel = $labels->singular('subseries');
        $periods = $this->partitionSeasonDates($seasonFrom, $seasonTo, $count);
        $created = 0;
        $failed = [];

        foreach ($periods as $i => $period) {
            $n = $i + 1;
            $data = [
                'owner_org_id' => $ownerOrgId,
                'space_id' => $spaceId,
                'parent_series_id' => $seasonRootId,
                'name' => $roundLabel . ' ' . $n,
                'short_name' => null,
                'description' => null,
                'series_type' => 'round',
                'season_label' => null,
                'sort_order' => $n,
                'starts_at' => $this->normalizeSeriesDateBound($period['starts_on'], false),
                'ends_at' => $this->normalizeSeriesDateBound($period['ends_on'], true),
                'status' => 'active',
                'visibility' => (string) ($seasonFull['visibility'] ?? 'internal'),
            ];
            $newId = $services->series->create($personId, $orgId, $data, $userId > 0 ? $userId : null);
            if ($newId === null) {
                $failed[] = $data['name'];
                continue;
            }
            ++$created;
        }

        if ($created === 0) {
            PortalV3Session::setFlash('error', 'Kunne ikke opprette runder.');
        } elseif ($failed !== []) {
            PortalV3Session::setFlash(
                'error',
                'Opprettet ' . $created . ' runde(r), men feilet for: ' . implode(', ', $failed),
            );
        } else {
            PortalV3Session::setFlash('success', $created . ' runder opprettet. Juster datoene ved behov og lagre.');
        }

        return Response::redirect(PortalPaths::cup() . '#season-' . $seasonRootId);
    }

    /**
     * Finn serie + org brukeren kan aksessere (aktiv org først, deretter øvrige admin-orgs).
     *
     * @return array{series: array<string, mixed>, org_id: int}|null
     */
    private function resolveSeriesAccess(PortalV3Services $services, int $personId, int $seriesId): ?array
    {
        $activeOrgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        if ($activeOrgId > 0) {
            $series = $services->series->findAccessible($personId, $seriesId, $activeOrgId);
            if ($series !== null) {
                return ['series' => $series, 'org_id' => $activeOrgId];
            }
        }

        foreach ($services->organizationContext->administrableOrganizations($personId) as $org) {
            $orgId = (int) ($org['org_id'] ?? 0);
            if ($orgId <= 0 || $orgId === $activeOrgId) {
                continue;
            }
            $series = $services->series->findAccessible($personId, $seriesId, $orgId);
            if ($series === null) {
                continue;
            }
            $services->organizationContext->setActiveOrganization($orgId, $personId, false);

            return ['series' => $series, 'org_id' => $orgId];
        }

        return null;
    }

    /**
     * Del sesongintervall i N sammenhengende, ikke-overlappende perioder (inkl. start/slutt).
     *
     * @return list<array{starts_on: string, ends_on: string}>
     */
    private function partitionSeasonDates(string $seasonFrom, string $seasonTo, int $count): array
    {
        $start = new \DateTimeImmutable($seasonFrom);
        $end = new \DateTimeImmutable($seasonTo);
        $totalDays = (int) $start->diff($end)->days + 1;
        if ($totalDays < 1) {
            $totalDays = 1;
        }
        if ($count > $totalDays) {
            $count = $totalDays;
        }

        $periods = [];
        for ($i = 0; $i < $count; ++$i) {
            $segStart = (int) floor($i * $totalDays / $count);
            $segEnd = (int) floor(($i + 1) * $totalDays / $count) - 1;
            if ($segEnd < $segStart) {
                $segEnd = $segStart;
            }
            $from = $start->modify('+' . $segStart . ' days')->format('Y-m-d');
            $to = $start->modify('+' . $segEnd . ' days')->format('Y-m-d');
            $periods[] = ['starts_on' => $from, 'ends_on' => $to];
        }

        return $periods;
    }

    /**
     * Runder (underserier) er bare tillatt når sesongstrukturen er «rounds».
     *
     * @param array<string, mixed> $seasonRoot
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    private function blockUnlessRoundsStructure(array $seasonRoot): ?array
    {
        if ((int) ($seasonRoot['parent_series_id'] ?? 0) > 0) {
            return null;
        }

        $structure = (string) ($seasonRoot['structure_type'] ?? '');
        $seriesId = (int) ($seasonRoot['series_id'] ?? 0);
        if ($structure === 'rounds') {
            return null;
        }

        if ($structure === '') {
            PortalV3Session::setFlash(
                'error',
                'Sett sesongstruktur til «stevner gruppert i runder» før du oppretter runder.',
            );
        } else {
            PortalV3Session::setFlash(
                'error',
                'Denne sesongen har stevner direkte — runder kan ikke opprettes.',
            );
        }

        return Response::redirect(
            $seriesId > 0 ? PortalPaths::sesongStruktur($seriesId) : PortalPaths::cup()
        );
    }

    /** Lagre dato som start/slutt av dag (DATETIME). */
    private function normalizeSeriesDateBound(string $ymd, bool $endOfDay): ?string
    {
        $ymd = trim($ymd);
        if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return null;
        }

        return $endOfDay ? ($ymd . ' 23:59:59') : ($ymd . ' 00:00:00');
    }

    private function toDateOnly(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
