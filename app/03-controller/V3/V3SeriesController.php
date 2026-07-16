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

        return Response::redirect(PortalPaths::sesongStruktur($seriesId));
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
            'status' => (string) ($_POST['status'] ?? 'active'),
            'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
        ];

        if ($data['name'] === '') {
            PortalV3Session::setFlash('error', 'Navn er påkrevd.');

            return $seriesId !== null
                ? Response::redirect(PortalPaths::sesongEdit($seriesId))
                : Response::redirect(PortalPaths::cup());
        }

        if ($seriesId === null) {
            $newId = $services->series->create($personId, $orgId, $data, $userId > 0 ? $userId : null);
            if ($newId === null) {
                PortalV3Session::setFlash('error', 'Kunne ikke opprette serie.');

                return Response::redirect(PortalPaths::cup());
            }
            PortalV3Session::setFlash('success', 'Serie opprettet.');

            return Response::redirect(PortalPaths::sesongEdit($newId));
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
}
