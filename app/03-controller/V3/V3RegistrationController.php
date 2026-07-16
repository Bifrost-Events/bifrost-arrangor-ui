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

final class V3RegistrationController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function index(int $eventId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $query = [
            'registration_status' => trim((string) ($_GET['registration_status'] ?? '')) ?: null,
            'attendance_status' => trim((string) ($_GET['attendance_status'] ?? '')) ?: null,
            'q' => trim((string) ($_GET['q'] ?? '')) ?: null,
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'per_page' => 50,
        ];
        $result = $api->listEventRegistrations($ctx['org_id'], $eventId, array_filter(
            $query,
            static fn ($v) => $v !== null && $v !== ''
        ));

        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Kunne ikke hente påmeldinger.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }

        return PortalV3View::render('events/registrations', [
            'space' => $ctx['space'],
            'event' => $ctx['event'],
            'labels' => $ctx['labels'],
            'payload' => $result['data'],
            'filters' => [
                'registration_status' => (string) ($_GET['registration_status'] ?? ''),
                'attendance_status' => (string) ($_GET['attendance_status'] ?? ''),
                'q' => (string) ($_GET['q'] ?? ''),
            ],
        ], 'Påmeldinger — ' . $ctx['labels']->singular('event'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createForm(int $eventId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        return PortalV3View::render('events/registration-create', [
            'space' => $ctx['space'],
            'event' => $ctx['event'],
            'labels' => $ctx['labels'],
            'candidates' => [],
            'form' => [
                'person_id' => '',
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'birth_date' => '',
                'notes' => '',
                'force_capacity_override' => false,
            ],
        ], 'Manuell påmelding');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createSubmit(int $eventId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        $body = [
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'force_capacity_override' => !empty($_POST['force_capacity_override']),
            'confirm_create' => !empty($_POST['confirm_create']),
        ];
        $personId = (int) ($_POST['person_id'] ?? 0);
        if ($personId > 0) {
            $body['person_id'] = $personId;
        } else {
            $body['first_name'] = trim((string) ($_POST['first_name'] ?? ''));
            $body['last_name'] = trim((string) ($_POST['last_name'] ?? ''));
            $body['email'] = trim((string) ($_POST['email'] ?? '')) ?: null;
            $body['phone'] = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $body['birth_date'] = trim((string) ($_POST['birth_date'] ?? '')) ?: null;
        }

        $api = new EventsApiClient();
        $result = $api->createEventRegistration($ctx['org_id'], $eventId, $body);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Påmelding opprettet.');
            $regId = (int) ($result['data']['registration_id'] ?? 0);

            return $regId > 0
                ? Response::redirect(PortalPaths::stevnePamelding($eventId, $regId))
                : Response::redirect(PortalPaths::stevnePameldinger($eventId));
        }

        if (($result['status'] ?? 0) === 409 && !empty($result['candidates'])) {
            return PortalV3View::render('events/registration-create', [
                'space' => $ctx['space'],
                'event' => $ctx['event'],
                'labels' => $ctx['labels'],
                'candidates' => $result['candidates'],
                'form' => [
                    'person_id' => (string) ($_POST['person_id'] ?? ''),
                    'first_name' => (string) ($_POST['first_name'] ?? ''),
                    'last_name' => (string) ($_POST['last_name'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'phone' => (string) ($_POST['phone'] ?? ''),
                    'birth_date' => (string) ($_POST['birth_date'] ?? ''),
                    'notes' => (string) ($_POST['notes'] ?? ''),
                    'force_capacity_override' => !empty($_POST['force_capacity_override']),
                ],
            ], 'Manuell påmelding — mulige duplikater');
        }

        PortalV3Session::setFlash('error', $result['error'] ?? 'Kunne ikke opprette påmelding.');

        return Response::redirect(PortalPaths::stevnePameldingNy($eventId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function show(int $eventId, int $registrationId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $result = $api->getRegistration($ctx['org_id'], $registrationId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Påmelding ikke funnet.');

            return Response::redirect(PortalPaths::stevnePameldinger($eventId));
        }

        $reg = $result['data']['registration'] ?? [];
        if ((int) ($reg['event_id'] ?? 0) !== $eventId) {
            PortalV3Session::setFlash('error', 'Påmeldingen tilhører ikke dette arrangementet.');

            return Response::redirect(PortalPaths::stevnePameldinger($eventId));
        }

        return PortalV3View::render('events/registration-show', [
            'space' => $ctx['space'],
            'event' => $ctx['event'],
            'labels' => $ctx['labels'],
            'registration' => $reg,
            'allowed' => $result['data']['allowed_transitions'] ?? [],
        ], 'Påmelding');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function updateSubmit(int $eventId, int $registrationId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        $body = [];
        if (isset($_POST['registration_status']) && $_POST['registration_status'] !== '') {
            $body['registration_status'] = (string) $_POST['registration_status'];
        }
        if (array_key_exists('attendance_status', $_POST)) {
            $att = (string) $_POST['attendance_status'];
            $body['attendance_status'] = $att === '' ? null : $att;
        }
        if (array_key_exists('notes', $_POST)) {
            $body['notes'] = trim((string) $_POST['notes']) ?: null;
        }
        if (!empty($_POST['reactivate'])) {
            $body['reactivate'] = true;
        }
        if (!empty($_POST['force_capacity_override'])) {
            $body['force_capacity_override'] = true;
        }

        $api = new EventsApiClient();
        $result = $api->updateRegistration($ctx['org_id'], $registrationId, $body);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Påmelding oppdatert.');
        } else {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Kunne ikke oppdatere.');
        }

        return Response::redirect(PortalPaths::stevnePamelding($eventId, $registrationId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function export(int $eventId): array
    {
        $ctx = $this->requireManageContext($eventId);
        if (!is_array($ctx) || isset($ctx['status'])) {
            return $ctx;
        }

        $api = new EventsApiClient();
        $result = $api->exportEventRegistrations($ctx['org_id'], $eventId);
        if (!($result['ok'] ?? false) || !is_array($result['data'])) {
            PortalV3Session::setFlash('error', $result['error'] ?? 'Eksport feilet.');

            return Response::redirect(PortalPaths::stevnePameldinger($eventId));
        }

        $csv = (string) ($result['data']['csv'] ?? '');
        $filename = (string) ($result['data']['filename'] ?? 'pameldinger.csv');

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            'body' => "\xEF\xBB\xBF" . $csv,
        ];
    }

    /**
     * @return array{org_id: int, event: array<string, mixed>, space: array<string, mixed>|null, labels: mixed}|array{status: int, headers: array<string, string>, body: string}
     */
    private function requireManageContext(int $eventId): array
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
        if (!$services->eventPolicy->canManageRegistrations($personId, $event, $orgId)) {
            PortalV3Session::setFlash('error', 'Du kan ikke administrere påmeldinger for dette arrangementet.');

            return Response::redirect(PortalPaths::stevne($eventId));
        }

        $spaceId = (int) ($event['space_id'] ?? 0);
        PortalV3Session::setSpaceId($spaceId > 0 ? $spaceId : null);
        $space = $spaceId > 0 ? $services->eventSpaces->findAccessibleForPerson($personId, $spaceId) : null;
        if (is_array($space)) {
            $access = (new PortalCupAccess($services))->forSpace($personId, $space);
            (new PortalWorkContext($services))->syncFromEvent($personId, $event, $space, $access);
        }
        $labels = $services->labels->resolveForSpace(is_array($space) ? $space : []);

        return [
            'org_id' => $orgId,
            'event' => $event,
            'space' => is_array($space) ? $space : null,
            'labels' => $labels,
        ];
    }
}
