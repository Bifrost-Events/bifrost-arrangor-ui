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

        $canCreatePolicy = $services->eventPolicy->canCreate($personId, $orgId, $orgId);
        $structureOk = $this->seriesAllowsDirectEvents($services, $personId, $orgId, $series);

        return PortalV3View::render('events/index', [
            'space' => $space,
            'series' => $series,
            'events' => $eventRows,
            'labels' => $labels,
            'can_create' => $canCreatePolicy && $structureOk,
            'structure_blocks_create' => $canCreatePolicy && !$structureOk,
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

        if ($event === null && !$this->seriesAllowsDirectEvents($services, $personId, $orgId, $series)) {
            return $this->redirectBlockedByStructure($series);
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

    /** Batch-opprett stevner for alle runder under en sesongrot. */
    public function batchCreateForm(int $spaceId, int $seasonRootId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $prepared = $this->prepareBatchContext($services, $personId, $orgId, $spaceId, $seasonRootId);
        if ($prepared['redirect'] !== null) {
            return $prepared['redirect'];
        }

        return $this->renderBatchForm($services, $prepared, null);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function batchCreateSubmit(int $spaceId, int $seasonRootId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requireOrganizationContext($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $prepared = $this->prepareBatchContext($services, $personId, $orgId, $spaceId, $seasonRootId);
        if ($prepared['redirect'] !== null) {
            return $prepared['redirect'];
        }

        $postedRounds = is_array($_POST['rounds'] ?? null) ? $_POST['rounds'] : [];
        $forceOutside = isset($_POST['force_outside_dates']) && (string) $_POST['force_outside_dates'] === '1';
        $failed = [];
        $postedRows = [];
        $outsideWarnings = [];
        $candidates = [];

        foreach ($prepared['rounds'] as $round) {
            $rid = (int) ($round['series_id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $row = is_array($postedRounds[$rid] ?? null) ? $postedRounds[$rid] : [];
            $name = trim((string) ($row['name'] ?? ''));
            $location = trim((string) ($row['location_name'] ?? ''));
            $startDate = trim((string) ($row['start_date'] ?? ''));
            $startTime = trim((string) ($row['start_time'] ?? ''));
            $startsAt = $this->combineDateAndTime($startDate, $startTime);
            $roundLabel = trim((string) ($round['name'] ?? $round['season_label'] ?? ('Runde #' . $rid)));
            $interval = $this->roundDateInterval($round);
            $outside = $startDate !== '' && $this->dateOutsideRoundInterval($startDate, $interval['from'], $interval['to']);
            $postedRows[] = [
                'series_id' => $rid,
                'round_label' => $roundLabel,
                'name' => $name,
                'location_name' => $location,
                'start_date' => $startDate,
                'start_time' => $startTime !== '' ? $startTime : '10:00',
                'round_starts_on' => $interval['from'],
                'round_ends_on' => $interval['to'],
                'date_warning' => $outside
                    ? $this->outsideIntervalMessage($roundLabel, $interval['from'], $interval['to'])
                    : null,
            ];

            if ($name === '') {
                continue;
            }

            if ($outside) {
                $outsideWarnings[] = $this->outsideIntervalMessage($roundLabel, $interval['from'], $interval['to']);
            }

            $candidates[] = [
                'round' => $round,
                'round_label' => $roundLabel,
                'outside' => $outside,
                'data' => [
                    'owner_org_id' => $orgId,
                    'series_id' => $rid,
                    'name' => $name,
                    'short_name' => null,
                    'description' => null,
                    'location_name' => $location !== '' ? $location : null,
                    'starts_at' => $startsAt !== '' ? $startsAt : null,
                    'ends_at' => null,
                    'max_participants' => null,
                    'status' => 'draft',
                    'visibility' => 'internal',
                ],
            ];
        }

        if ($candidates === []) {
            return $this->renderBatchForm(
                $services,
                $prepared,
                $postedRows,
                'Fyll inn minst ett stevnenavn for å opprette.',
            );
        }

        if ($outsideWarnings !== [] && !$forceOutside) {
            return $this->renderBatchForm(
                $services,
                $prepared,
                $postedRows,
                'Stevnedato er utenfor rundens intervall. Juster datoen, eller lagre likevel.',
                true,
            );
        }

        $created = 0;
        foreach ($candidates as $candidate) {
            $round = $candidate['round'];
            $roundLabel = (string) $candidate['round_label'];
            if (!$this->seriesAllowsDirectEvents($services, $personId, $orgId, $round)) {
                $failed[] = $roundLabel . ': kan ikke opprette under denne serien';
                continue;
            }

            $newId = $services->events->create(
                $personId,
                $orgId,
                $candidate['data'],
                $userId > 0 ? $userId : null,
            );
            if ($newId === null) {
                $failed[] = $roundLabel . ': kunne ikke opprettes';
                continue;
            }
            ++$created;
        }

        if ($failed !== [] && $created === 0) {
            return $this->renderBatchForm(
                $services,
                $prepared,
                $postedRows,
                'Ingen stevner ble opprettet. ' . implode(' ', $failed),
            );
        }

        $msg = $created === 1
            ? '1 stevne opprettet.'
            : ($created . ' stevner opprettet.');
        if ($failed !== []) {
            $msg .= ' Noen feilet: ' . implode(' ', $failed);
        }
        if ($outsideWarnings !== [] && $forceOutside) {
            $msg .= ' Merk: noen datoer er utenfor rundens intervall.';
        }
        PortalV3Session::setFlash($failed !== [] ? 'error' : 'success', $msg);

        return Response::redirect(PortalPaths::stevner() . '?season_scope=all');
    }

    /**
     * @param array{
     *   redirect: array{status: int, headers: array<string, string>, body: string}|null,
     *   space: array<string, mixed>,
     *   season: array<string, mixed>,
     *   rounds: list<array<string, mixed>>
     * } $prepared
     * @param list<array<string, mixed>>|null $overrideRows
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function renderBatchForm(
        PortalV3Services $services,
        array $prepared,
        ?array $overrideRows,
        ?string $errorMessage = null,
        bool $showForceOutside = false,
    ): array {
        $personId = PortalV3Auth::personId() ?? 0;
        $org = $services->organizationContext->resolveActiveOrganization($personId);
        $orgLabel = trim((string) ($org['short_name'] ?? $org['name'] ?? ''));
        if ($orgLabel === '') {
            $orgLabel = 'Stevne';
        }

        $rows = $overrideRows;
        if ($rows === null) {
            $rows = [];
            foreach ($prepared['rounds'] as $round) {
                $rid = (int) ($round['series_id'] ?? 0);
                $roundLabel = trim((string) ($round['name'] ?? $round['season_label'] ?? ('Runde #' . $rid)));
                $interval = $this->roundDateInterval($round);
                $rows[] = [
                    'series_id' => $rid,
                    'round_label' => $roundLabel,
                    'name' => $orgLabel . ' – ' . $roundLabel,
                    'location_name' => '',
                    'start_date' => '',
                    'start_time' => '10:00',
                    'round_starts_on' => $interval['from'],
                    'round_ends_on' => $interval['to'],
                    'date_warning' => null,
                ];
            }
        } else {
            foreach ($rows as $i => $row) {
                if (!array_key_exists('round_starts_on', $row) || !array_key_exists('round_ends_on', $row)) {
                    $rid = (int) ($row['series_id'] ?? 0);
                    foreach ($prepared['rounds'] as $round) {
                        if ((int) ($round['series_id'] ?? 0) === $rid) {
                            $interval = $this->roundDateInterval($round);
                            $rows[$i]['round_starts_on'] = $interval['from'];
                            $rows[$i]['round_ends_on'] = $interval['to'];
                            break;
                        }
                    }
                }
            }
        }

        $spaceId = (int) ($prepared['space']['space_id'] ?? 0);
        PortalV3Session::setSpaceId($spaceId > 0 ? $spaceId : null);
        $labels = $services->labels->resolveForSpace($prepared['space']);

        if ($errorMessage !== null && $errorMessage !== '') {
            PortalV3Session::setFlash('error', $errorMessage);
        }

        return PortalV3View::render('events/batch-form', [
            'space' => $prepared['space'],
            'season' => $prepared['season'],
            'rows' => $rows,
            'labels' => $labels,
            'show_force_outside' => $showForceOutside,
        ], 'Opprett stevner');
    }

    /** @return array{from: string|null, to: string|null} */
    private function roundDateInterval(array $round): array
    {
        return [
            'from' => $this->toDateOnly($round['starts_at'] ?? null),
            'to' => $this->toDateOnly($round['ends_at'] ?? null),
        ];
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

    private function dateOutsideRoundInterval(string $date, ?string $from, ?string $to): bool
    {
        $date = $this->toDateOnly($date);
        if ($date === null) {
            return false;
        }
        if ($from !== null && $date < $from) {
            return true;
        }
        if ($to !== null && $date > $to) {
            return true;
        }

        return false;
    }

    private function outsideIntervalMessage(string $roundLabel, ?string $from, ?string $to): string
    {
        $period = match (true) {
            $from !== null && $to !== null => $from . ' – ' . $to,
            $from !== null => 'fra ' . $from,
            $to !== null => 'til ' . $to,
            default => 'ukjent intervall',
        };

        return $roundLabel . ': stevnedato er utenfor rundens intervall (' . $period . ').';
    }

    private function combineDateAndTime(string $date, string $time): string
    {
        $date = $this->toDateOnly($date) ?? '';
        if ($date === '') {
            return '';
        }
        $time = trim($time);
        if ($time === '') {
            $time = '10:00';
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return $date . ' ' . $time;
    }

    /**
     * @return array{
     *   redirect: array{status: int, headers: array<string, string>, body: string}|null,
     *   space: array<string, mixed>,
     *   season: array<string, mixed>,
     *   rounds: list<array<string, mixed>>
     * }
     */
    private function prepareBatchContext(
        PortalV3Services $services,
        int $personId,
        int $orgId,
        int $spaceId,
        int $seasonRootId,
    ): array {
        $empty = ['redirect' => null, 'space' => [], 'season' => [], 'rounds' => []];
        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        $season = $services->series->findAccessible($personId, $seasonRootId, $orgId);
        if ($space === null || $season === null || (int) ($season['space_id'] ?? 0) !== $spaceId) {
            PortalV3Session::setFlash('error', 'Sesong ikke funnet eller ingen tilgang.');

            return array_merge($empty, [
                'redirect' => Response::redirect(PortalPaths::stevner() . '?season_scope=all'),
            ]);
        }

        if (!$services->eventPolicy->canCreate($personId, $orgId, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen opprettingstilgang.');

            return array_merge($empty, [
                'redirect' => Response::redirect(PortalPaths::stevner() . '?season_scope=all'),
            ]);
        }

        $parentId = (int) ($season['parent_series_id'] ?? 0);
        if ($parentId > 0) {
            PortalV3Session::setFlash('error', 'Batch-opprettelse gjelder sesongen, ikke en enkelt runde.');

            return array_merge($empty, [
                'redirect' => Response::redirect(PortalPaths::sesongStevneNew($seasonRootId)),
            ]);
        }

        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $rounds = $hierarchy['children'][$seasonRootId] ?? [];
        if ($rounds === []) {
            $rounds = $services->spaceParticipation->listChildSeriesByParentId($seasonRootId);
        }
        if ($rounds === []) {
            PortalV3Session::setFlash(
                'error',
                'Denne sesongen har ingen runder. Opprett ett stevne direkte i stedet.',
            );
            $structure = (string) ($season['structure_type'] ?? '');
            $target = $structure === 'events'
                ? PortalPaths::sesongStevneNew($seasonRootId)
                : (PortalPaths::stevner() . '?season_scope=all');

            return array_merge($empty, ['redirect' => Response::redirect($target)]);
        }

        // Sørg for starts_at/ends_at (hierarki-API kan mangle feltene).
        $dbById = [];
        foreach ($services->spaceParticipation->listChildSeriesByParentId($seasonRootId) as $dbRound) {
            $dbById[(int) ($dbRound['series_id'] ?? 0)] = $dbRound;
        }
        foreach ($rounds as $i => $round) {
            $rid = (int) ($round['series_id'] ?? 0);
            if ($rid <= 0 || !isset($dbById[$rid])) {
                continue;
            }
            if (!array_key_exists('starts_at', $round) || ($round['starts_at'] ?? null) === null || ($round['starts_at'] ?? '') === '') {
                $rounds[$i]['starts_at'] = $dbById[$rid]['starts_at'] ?? null;
            }
            if (!array_key_exists('ends_at', $round) || ($round['ends_at'] ?? null) === null || ($round['ends_at'] ?? '') === '') {
                $rounds[$i]['ends_at'] = $dbById[$rid]['ends_at'] ?? null;
            }
        }

        $allowedRounds = [];
        foreach ($rounds as $round) {
            if ($this->seriesAllowsDirectEvents($services, $personId, $orgId, $round)) {
                $allowedRounds[] = $round;
            }
        }
        if ($allowedRounds === []) {
            PortalV3Session::setFlash('error', 'Ingen runder tillater stevneopprettelse.');

            return array_merge($empty, [
                'redirect' => Response::redirect(PortalPaths::stevner() . '?season_scope=all'),
            ]);
        }

        return [
            'redirect' => null,
            'space' => $space,
            'season' => $season,
            'rounds' => $allowedRounds,
        ];
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
            $series = $services->series->findAccessible($personId, $seriesId, $orgId);
            if ($series === null || !$this->seriesAllowsDirectEvents($services, $personId, $orgId, $series)) {
                return $this->redirectBlockedByStructure($series ?? ['series_id' => $seriesId]);
            }

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

    /**
     * Stevner kan ligge direkte på sesong-root kun ved structure_type=events.
     * Ved rundestruktur opprettes stevner under runden (child series).
     *
     * @param array<string, mixed> $series
     */
    private function seriesAllowsDirectEvents(
        PortalV3Services $services,
        int $personId,
        int $orgId,
        array $series,
    ): bool {
        $parentId = (int) ($series['parent_series_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $services->series->findAccessible($personId, $parentId, $orgId);
            if ($parent === null) {
                return false;
            }
            // Runde under sesong: tillat når sesongen er rounds (eller uavklart men runde finnes).
            $structure = (string) ($parent['structure_type'] ?? '');

            return $structure === '' || $structure === 'rounds';
        }

        return (string) ($series['structure_type'] ?? '') === 'events'
            || (string) ($series['structure_type'] ?? '') === ''
            || (
                (string) ($series['structure_type'] ?? '') === 'rounds'
                && $services->spaceParticipation->listChildSeriesByParentId((int) ($series['series_id'] ?? 0)) === []
            );
    }

    /**
     * @param array<string, mixed> $series
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function redirectBlockedByStructure(array $series): array
    {
        $seriesId = (int) ($series['series_id'] ?? 0);
        $parentId = (int) ($series['parent_series_id'] ?? 0);
        $rootId = $parentId > 0 ? $parentId : $seriesId;
        $structure = (string) ($series['structure_type'] ?? '');
        $fallback = PortalV3Session::getWorkMode() === PortalV3Session::WORK_MODE_ARRANGER
            ? PortalPaths::stevner() . '?season_scope=all'
            : PortalPaths::cup();

        if ($parentId === 0 && $structure === '') {
            PortalV3Session::setFlash(
                'error',
                'Sett sesongstruktur før du oppretter stevner.',
            );

            return Response::redirect(
                $rootId > 0 && PortalV3Session::getWorkMode() !== PortalV3Session::WORK_MODE_ARRANGER
                    ? PortalPaths::sesongStruktur($rootId)
                    : $fallback
            );
        }

        if ($parentId === 0 && $structure === 'rounds') {
            $services = new PortalV3Services();
            $children = $services->spaceParticipation->listChildSeriesByParentId($seriesId);
            if ($children !== []) {
                PortalV3Session::setFlash(
                    'error',
                    'Ved rundestruktur opprettes stevner under en runde, ikke direkte i sesongen.',
                );

                return Response::redirect($fallback);
            }
            // Ingen runder opprettet ennå — tillat stevne direkte på sesongrot.
        }

        PortalV3Session::setFlash('error', 'Kan ikke opprette stevne her ut fra sesongstrukturen.');

        return Response::redirect($fallback);
    }
}
