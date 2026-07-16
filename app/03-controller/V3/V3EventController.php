<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Service\PortalWorkContext;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3EventController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function index(int $spaceId, int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($space === null || $series === null || (int) ($series['space_id'] ?? 0) !== $spaceId) {
            PortalV3Session::setFlash('error', 'Serie ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        PortalV3Session::setSpaceId($spaceId);
        $events = $services->events->listForSeries($personId, $seriesId, $orgId);
        $labels = $services->labels->resolveForSpace($space);

        $eventRows = [];
        foreach ($events as $event) {
            $eventRows[] = [
                'event' => $event,
                'can_edit' => $services->eventPolicy->canEdit($personId, $event, $orgId),
            ];
        }

        return PortalV3View::render('events/index', [
            'space' => $space,
            'series' => $series,
            'events' => $eventRows,
            'labels' => $labels,
            'can_create' => $services->eventPolicy->canCreate($personId, $orgId, $orgId),
        ], $labels->plural('event'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createForm(int $spaceId, int $seriesId): array
    {
        return $this->form($spaceId, $seriesId, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function editForm(int $eventId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $found = $services->events->findAccessibleForPerson($personId, $eventId);
        if ($found === null) {
            PortalV3Session::setFlash('error', 'Arrangement ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::stevner());
        }

        $event = $found['event'];
        $orgId = (int) $found['org_id'];
        if (!$services->eventPolicy->canEdit($personId, $event, $orgId)) {
            PortalV3Session::setFlash('error', 'Arrangement ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::stevner());
        }

        $spaceId = (int) ($event['space_id'] ?? 0);
        $seriesId = (int) ($event['series_id'] ?? 0);
        PortalV3Session::setSpaceId($spaceId > 0 ? $spaceId : null);

        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if (is_array($space)) {
            $access = (new PortalCupAccess($services))->forSpace($personId, $space);
            (new PortalWorkContext($services))->syncFromEvent($personId, $event, $space, $access);
        }

        return $this->form($spaceId, $seriesId, $event);
    }

    /**
     * @param array<string, mixed>|null $event
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function form(int $spaceId, int $seriesId, ?array $event): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($space === null || $series === null) {
            PortalV3Session::setFlash('error', 'Ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        if ($event !== null && !$services->eventPolicy->canEdit($personId, $event, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen redigeringstilgang.');

            return Response::redirect(PortalPaths::sesongStevner($seriesId));
        }

        if ($event === null && !$services->eventPolicy->canCreate($personId, $orgId, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen opprettingstilgang.');

            return Response::redirect(PortalPaths::sesongStevner($seriesId));
        }

        PortalV3Session::setSpaceId($spaceId);
        $labels = $services->labels->resolveForSpace($space);

        return PortalV3View::render('events/form', [
            'space' => $space,
            'series' => $series,
            'event' => $event,
            'labels' => $labels,
            'is_edit' => $event !== null,
        ], ($event !== null ? 'Rediger ' : 'Nytt ') . $labels->singular('event'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createSubmit(int $spaceId, int $seriesId): array
    {
        return $this->save($spaceId, $seriesId, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function updateSubmit(int $eventId): array
    {
        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $event = $services->events->findAccessible($personId, $eventId, $orgId);
        if ($event === null) {
            PortalV3Session::setFlash('error', 'Arrangement ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        return $this->save((int) ($event['space_id'] ?? 0), (int) ($event['series_id'] ?? 0), $eventId);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function save(int $spaceId, int $seriesId, ?int $eventId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);

        $data = [
            'owner_org_id' => $orgId,
            'series_id' => $seriesId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'location_name' => trim((string) ($_POST['location_name'] ?? '')) ?: null,
            'starts_at' => trim((string) ($_POST['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($_POST['ends_at'] ?? '')) ?: null,
            'max_participants' => ($_POST['max_participants'] ?? '') !== '' ? (int) $_POST['max_participants'] : null,
            'status' => (string) ($_POST['status'] ?? 'draft'),
            'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Navn er påkrevd.';
        }
        if ($errors !== []) {
            PortalV3Session::setFlash('error', 'Skjemaet har feil.', $errors);

            return $eventId !== null
                ? Response::redirect(PortalPaths::stevne($eventId))
                : Response::redirect(PortalPaths::sesongStevneNew($seriesId));
        }

        if ($eventId === null) {
            $newId = $services->events->create($personId, $orgId, $data, $userId > 0 ? $userId : null);
            if ($newId === null) {
                PortalV3Session::setFlash('error', 'Kunne ikke opprette arrangement.');

                return Response::redirect(PortalPaths::sesongStevner($seriesId));
            }
            PortalV3Session::setFlash('success', 'Arrangement opprettet.');

            return Response::redirect(PortalPaths::stevne($newId));
        }

        if (!$services->events->update($personId, $orgId, $eventId, $data, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke lagre arrangement.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }

        PortalV3Session::setFlash('success', 'Arrangement lagret.');

        return Response::redirect(PortalPaths::stevne($eventId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function archiveSubmit(int $eventId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $event = $services->events->findAccessible($personId, $eventId, $orgId);
        if ($event === null) {
            PortalV3Session::setFlash('error', 'Arrangement ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $spaceId = (int) ($event['space_id'] ?? 0);
        $seriesId = (int) ($event['series_id'] ?? 0);
        if (!$services->events->archive($personId, $orgId, $eventId, $userId > 0 ? $userId : null)) {
            PortalV3Session::setFlash('error', 'Kunne ikke arkivere arrangement.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }

        PortalV3Session::setFlash('success', 'Arrangement arkivert.');

        return Response::redirect(PortalPaths::sesongStevner($seriesId));
    }
}
