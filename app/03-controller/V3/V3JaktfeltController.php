<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\EventsApiClient;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Service\PortalWorkContext;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

/**
 * Arrangør-UI for jaktfelt-grid (V3).
 */
final class V3JaktfeltController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function grid(int $eventId): array
    {
        $ctx = $this->requireManage($eventId);
        if (isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $result = $api->getJaktfeltSlotGrid($ctx['org_id'], $eventId);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Kunne ikke hente grid.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }

        return PortalV3View::render('events/jaktfelt-grid', [
            'space' => $ctx['space'],
            'event' => $ctx['event'],
            'labels' => $ctx['labels'],
            'grid' => $result['data'],
        ], 'Jaktfelt-grid');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function generateSubmit(int $eventId): array
    {
        $ctx = $this->requireManage($eventId);
        if (isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $result = $api->putJaktfeltSlotGrid($ctx['org_id'], $eventId, [
            'slot_count' => (int) ($_POST['slot_count'] ?? 0),
            'positions_per_slot' => (int) ($_POST['positions_per_slot'] ?? 0),
            'reserved_slots' => (int) ($_POST['reserved_slots'] ?? 0),
            'first_starts_at' => trim((string) ($_POST['first_starts_at'] ?? '')) ?: null,
            'minutes_between_slots' => ($_POST['minutes_between_slots'] ?? '') !== ''
                ? (int) $_POST['minutes_between_slots'] : null,
        ]);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Grid generert / oppdatert.');
        } else {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Grid-generering feilet.');
        }

        return Response::redirect(PortalPaths::stevneJaktfelt($eventId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function moveSubmit(int $eventId): array
    {
        $ctx = $this->requireManage($eventId);
        if (isset($ctx['status'])) {
            return $ctx;
        }

        $regId = (int) ($_POST['registration_id'] ?? 0);
        $api = new EventsApiClient();
        $result = $api->moveJaktfeltRegistration($ctx['org_id'], $regId, [
            'target_slot_position_id' => (int) ($_POST['target_slot_position_id'] ?? 0),
        ]);
        PortalV3Session::setFlash(
            ($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false) ? 'Deltaker flyttet.' : ($result['error'] ?? 'Flytting feilet.')
        );

        return Response::redirect(PortalPaths::stevneJaktfelt($eventId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function registerSubmit(int $eventId): array
    {
        $ctx = $this->requireManage($eventId);
        if (isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $result = $api->createJaktfeltOrganizerRegistration($ctx['org_id'], $eventId, [
            'person_id' => (int) ($_POST['person_id'] ?? 0),
            'slot_position_id' => (int) ($_POST['slot_position_id'] ?? 0),
            'class_name' => trim((string) ($_POST['class_name'] ?? '')),
            'class_key' => trim((string) ($_POST['class_key'] ?? '')) ?: null,
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);
        PortalV3Session::setFlash(
            ($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false) ? 'Manuell påmelding opprettet.' : ($result['error'] ?? 'Feilet.')
        );

        return Response::redirect(PortalPaths::stevneJaktfelt($eventId));
    }

    /**
     * @return array{org_id: int, event: array<string, mixed>, space: array<string, mixed>|null, labels: mixed}|array{status: int, headers: array<string, string>, body: string}
     */
    private function requireManage(int $eventId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }
        $personId = PortalV3Auth::personId() ?? 0;
        $found = $services->events->findAccessibleForPerson($personId, $eventId);
        if ($found === null) {
            PortalV3Session::setFlash('error', 'Arrangement ikke funnet.');

            return Response::redirect(PortalPaths::stevner());
        }
        $event = $found['event'];
        $orgId = (int) $found['org_id'];
        if (!$services->eventPolicy->canManageRegistrations($personId, $event, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til jaktfelt-grid.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }
        $spaceId = (int) ($event['space_id'] ?? 0);
        PortalV3Session::setSpaceId($spaceId > 0 ? $spaceId : null);
        $space = $spaceId > 0 ? $services->eventSpaces->findAccessibleForPerson($personId, $spaceId) : null;
        if (is_array($space)) {
            $access = (new PortalCupAccess($services))->forSpace($personId, $space);
            (new PortalWorkContext($services))->syncFromEvent($personId, $event, $space, $access);
        }

        return [
            'org_id' => $orgId,
            'event' => $event,
            'space' => is_array($space) ? $space : null,
            'labels' => $services->labels->resolveForSpace(is_array($space) ? $space : []),
        ];
    }
}
