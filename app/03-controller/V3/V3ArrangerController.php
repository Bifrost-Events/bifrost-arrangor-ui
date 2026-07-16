<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\CupArrangerService;
use App\Service\PortalBoundCup;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3ArrangerController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function index(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Cup ikke funnet.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        if (!($access['can_view_arrangers'] ?? false)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til arrangøroversikt.');

            return Response::redirect(PortalPaths::stevner());
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        PortalV3Session::setSpaceId($spaceId);

        $bound = (new PortalBoundCup($services))->resolve($personId);
        $payload = (new CupArrangerService($services))->listForSpace(
            $personId,
            $space,
            $orgId,
            $bound['season_series_id'] ?? null,
        );
        $labels = $services->labels->resolveForSpace($space);
        $canCreateForArranger = $this->canCreateForArranger($services, $personId, $space, $orgId);

        return PortalV3View::render('arrangers/index', [
            'space' => $space,
            'labels' => $labels,
            'season' => $payload['season'] ?? $bound['season'],
            'arrangers' => $payload['arrangers'],
            'can_create_for_arranger' => $canCreateForArranger,
        ], 'Arrangører');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function show(int $spaceId, int $arrangerOrgId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Cup ikke funnet.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        $canView = ($access['can_view_arrangers'] ?? false)
            || in_array($arrangerOrgId, $access['arranger_org_ids'] ?? [], true);
        if (!$canView) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til denne arrangøren.');

            return Response::redirect(PortalPaths::stevner());
        }

        $sessionOrgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        PortalV3Session::setSpaceId($spaceId);

        $bound = (new PortalBoundCup($services))->resolve($personId);
        $payload = (new CupArrangerService($services))->listForSpace(
            $personId,
            $space,
            $sessionOrgId,
            $bound['season_series_id'] ?? null,
        );
        $arranger = null;
        foreach ($payload['arrangers'] as $row) {
            if ((int) ($row['org_id'] ?? 0) === $arrangerOrgId) {
                $arranger = $row;
                break;
            }
        }
        if ($arranger === null) {
            PortalV3Session::setFlash('error', 'Arrangør ikke funnet i denne cupen.');

            return Response::redirect(PortalPaths::arrangorer());
        }

        $labels = $services->labels->resolveForSpace($space);

        return PortalV3View::render('arrangers/show', [
            'space' => $space,
            'labels' => $labels,
            'season' => $payload['season'],
            'arranger' => $arranger,
            'can_create_for_arranger' => $this->canCreateForArranger($services, $personId, $space, $sessionOrgId),
            'preset_owner_org_id' => $arrangerOrgId,
        ], (string) ($arranger['name'] ?? 'Arrangør'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createEventForm(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Cup ikke funnet.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $sessionOrgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        if (!$this->canCreateForArranger($services, $personId, $space, $sessionOrgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til å opprette stevne for ny arrangør.');

            return Response::redirect(PortalPaths::arrangorer());
        }

        PortalV3Session::setSpaceId($spaceId);
        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $sessionOrgId);
        $seriesOptions = $this->flattenSeriesOptions($hierarchy['roots'] ?? [], $hierarchy['children'] ?? []);
        $labels = $services->labels->resolveForSpace($space);
        $presetOwner = (int) ($_GET['owner_org_id'] ?? 0);
        $bound = (new PortalBoundCup($services))->resolve($personId);
        $presetSeries = (int) ($_GET['series_id'] ?? ($bound['season_series_id'] ?? 0));

        return PortalV3View::render('arrangers/create-event', [
            'space' => $space,
            'labels' => $labels,
            'series_options' => $seriesOptions,
            'organizations' => $services->organizations->listActive(),
            'preset_owner_org_id' => $presetOwner,
            'preset_series_id' => $presetSeries,
            'event' => null,
            'form_errors' => [],
        ], 'Opprett stevne for ny arrangør');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createEventSubmit(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Cup ikke funnet.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $sessionOrgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        if (!$this->canCreateForArranger($services, $personId, $space, $sessionOrgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect(PortalPaths::arrangorer());
        }

        $ownerOrgId = (int) ($_POST['owner_org_id'] ?? 0);
        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $series = $services->series->findAccessible($personId, $seriesId, $sessionOrgId);
        $errors = [];
        if ($ownerOrgId <= 0 || !$services->organizations->exists($ownerOrgId)) {
            $errors['owner_org_id'] = 'Velg en gyldig arrangørorganisasjon.';
        }
        if ($series === null || (int) ($series['space_id'] ?? 0) !== $spaceId) {
            $errors['series_id'] = 'Velg en gyldig sesong/serie i cupen.';
        }
        if ($name === '') {
            $errors['name'] = 'Stevnenavn er påkrevd.';
        }
        if ($series !== null
            && !$services->eventPolicy->canCreateInSpace($personId, $ownerOrgId, $sessionOrgId, $series, $space)) {
            $errors['owner_org_id'] = 'Ingen tilgang til å opprette stevne for valgt organisasjon.';
        }

        if ($errors !== []) {
            PortalV3Session::setFlash('error', 'Skjemaet har feil.');
            $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $sessionOrgId);

            return PortalV3View::render('arrangers/create-event', [
                'space' => $space,
                'labels' => $services->labels->resolveForSpace($space),
                'series_options' => $this->flattenSeriesOptions($hierarchy['roots'] ?? [], $hierarchy['children'] ?? []),
                'organizations' => $services->organizations->listActive(),
                'preset_owner_org_id' => $ownerOrgId,
                'preset_series_id' => $seriesId,
                'event' => [
                    'name' => $name,
                    'location_name' => trim((string) ($_POST['location_name'] ?? '')),
                    'starts_at' => trim((string) ($_POST['starts_at'] ?? '')),
                    'ends_at' => trim((string) ($_POST['ends_at'] ?? '')),
                    'status' => (string) ($_POST['status'] ?? 'draft'),
                    'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                ],
                'form_errors' => $errors,
            ], 'Opprett stevne for ny arrangør');
        }

        $data = [
            'owner_org_id' => $ownerOrgId,
            'series_id' => $seriesId,
            'name' => $name,
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'location_name' => trim((string) ($_POST['location_name'] ?? '')) ?: null,
            'starts_at' => trim((string) ($_POST['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($_POST['ends_at'] ?? '')) ?: null,
            'max_participants' => ($_POST['max_participants'] ?? '') !== '' ? (int) $_POST['max_participants'] : null,
            'status' => (string) ($_POST['status'] ?? 'draft'),
            'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
        ];

        $newId = $services->events->create(
            $personId,
            $sessionOrgId,
            $data,
            $userId > 0 ? $userId : null,
            $series,
            $space,
        );
        if ($newId === null || $newId <= 0) {
            PortalV3Session::setFlash('error', 'Kunne ikke opprette stevne (tilgang eller API-feil).');

            return Response::redirect(PortalPaths::arrangorNyttStevne());
        }

        return PortalV3View::render('arrangers/event-created', [
            'space' => $space,
            'labels' => $services->labels->resolveForSpace($space),
            'event_id' => $newId,
            'owner_org_id' => $ownerOrgId,
            'event_name' => $name,
        ], 'Stevne opprettet');
    }

    /** @param array<string, mixed> $space */
    private function canCreateForArranger(
        PortalV3Services $services,
        int $personId,
        array $space,
        int $sessionOrgId,
    ): bool {
        if ($sessionOrgId <= 0) {
            return false;
        }
        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        if (!($access['can_manage_cup'] ?? false)) {
            return false;
        }
        // Krever også policy-støtte for create-in-space med cup-org som aktiv org.
        $fakeSeries = [
            'series_id' => 1,
            'space_id' => (int) ($space['space_id'] ?? 0),
            'owner_org_id' => (int) ($space['owner_org_id'] ?? 0),
        ];

        return $services->eventPolicy->canCreateInSpace(
            $personId,
            $sessionOrgId,
            $sessionOrgId,
            $fakeSeries,
            $space,
        );
    }

    /**
     * @param list<array<string, mixed>> $roots
     * @param array<int, list<array<string, mixed>>> $children
     * @return list<array{series_id: int, label: string}>
     */
    private function flattenSeriesOptions(array $roots, array $children, string $prefix = ''): array
    {
        $out = [];
        foreach ($roots as $node) {
            $id = (int) ($node['series_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = (string) ($node['name'] ?? ('Serie #' . $id));
            $type = (string) ($node['series_type'] ?? '');
            $label = $prefix . $name . ($type !== '' ? ' (' . $type . ')' : '');
            $out[] = ['series_id' => $id, 'label' => $label];
            $kids = $children[$id] ?? [];
            if ($kids !== []) {
                $out = array_merge($out, $this->flattenSeriesOptions($kids, $children, $prefix . $name . ' → '));
            }
        }

        return $out;
    }
}
