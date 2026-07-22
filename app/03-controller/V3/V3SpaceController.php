<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\EventsApiClient;
use App\Service\PortalV3Services;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3SpaceController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function index(): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $domain = $services->domainContext->resolveFromRequest();
        $spaces = $services->eventSpaces->listAdministrable($personId);

        // Domene → applikasjon: hopp direkte inn i cupen (vanligvis én).
        if ($domain !== null && count($spaces) === 1) {
            $onlyId = (int) ($spaces[0]['space_id'] ?? 0);
            if ($onlyId > 0) {
                PortalV3Session::setSpaceId($onlyId);

                return Response::redirect(PortalPaths::cup());
            }
        }

        if ($domain === null) {
            PortalV3Session::setSpaceId(null);
        }

        return PortalV3View::render('spaces/index', [
            'spaces' => $spaces,
            'api_error' => EventsApiClient::lastListError(),
            'domain_application' => $domain,
            'domain_bound' => $domain !== null,
        ], $domain !== null
            ? (string) ($domain['application_name'] ?? 'Cuper')
            : 'Oversikt');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function show(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Event Space ikke funnet eller ingen tilgang.');

            return Response::redirect(PortalPaths::cups());
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        PortalV3Session::setSpaceId($spaceId);
        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $labels = $services->labels->resolveForSpace($space);

        return PortalV3View::render('spaces/show', [
            'space' => $space,
            'roots' => $hierarchy['roots'],
            'children' => $hierarchy['children'],
            'labels' => $labels,
            'can_edit_space' => $services->spacePolicy->canEdit($personId, $space, $orgId),
            'can_manage_series' => $services->spacePolicy->canManageSeries($personId, $space, $orgId),
            'can_create_series' => $services->seriesPolicy->canCreate($personId, $orgId, $orgId),
        ], (string) ($space['name'] ?? 'Event Space'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function editForm(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        if ($space === null || !$services->spacePolicy->canEdit($personId, $space, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen redigeringstilgang.');

            return Response::redirect(PortalPaths::cup());
        }

        PortalV3Session::setSpaceId($spaceId);
        $labels = $services->labels->resolveForSpace($space);

        return PortalV3View::render('spaces/form', [
            'space' => $space,
            'labels' => $labels,
        ], 'Rediger ' . $labels->singular('event_space'));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function editSubmit(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $userId = (int) (PortalV3Auth::user()['user_id'] ?? 0);
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        if ($space === null || !$services->spacePolicy->canEdit($personId, $space, $orgId)) {
            PortalV3Session::setFlash('error', 'Ingen redigeringstilgang.');

            return Response::redirect(PortalPaths::cup());
        }

        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'slug' => trim((string) ($_POST['slug'] ?? '')) ?: null,
            'status' => (string) ($_POST['status'] ?? 'active'),
            'visibility' => (string) ($_POST['visibility'] ?? 'internal'),
            'ui_labels_json' => trim((string) ($_POST['ui_labels_json'] ?? '')) ?: null,
        ];

        if ($data['name'] === '') {
            PortalV3Session::setFlash('error', 'Navn er påkrevd.');

            return Response::redirect(PortalPaths::cupEdit());
        }

        if (!$services->eventSpaces->update($personId, $orgId, $spaceId, $data, $userId > 0 ? $userId : null)) {
            $apiError = EventsApiClient::lastListError();
            PortalV3Session::setFlash(
                'error',
                $apiError !== null && $apiError !== ''
                    ? $apiError
                    : 'Kunne ikke lagre Event Space.',
            );

            return Response::redirect(PortalPaths::cupEdit());
        }

        PortalV3Session::setFlash('success', 'Event Space lagret.');

        return Response::redirect(PortalPaths::cup());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function listEvents(int $spaceId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Event Space ikke funnet.');

            return Response::redirect(PortalPaths::cups());
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        PortalV3Session::setSpaceId($spaceId);
        $bound = (new \App\Service\PortalBoundCup($services))->resolve($personId);
        $seasonSeriesIds = is_array($bound['season_series_ids'] ?? null) ? $bound['season_series_ids'] : [];
        $access = (new \App\Service\PortalCupAccess($services))->forSpace($personId, $space);
        $workCtx = new \App\Service\PortalWorkContext($services);
        $work = $workCtx->resolve($personId, $space, $access);
        $menuAccess = $workCtx->menuAccess($access, $work);
        $orgId = $workCtx->listOrgId($space, $access, $work);
        $events = $services->events->listForSpace($personId, $spaceId, $orgId);
        $events = $workCtx->filterEventsForWork($events, $menuAccess, $access);

        $isArrangerView = ($work['mode'] ?? '') === \App\Support\PortalV3Session::WORK_MODE_ARRANGER;
        $filterOrg = (int) ($_GET['organizer_id'] ?? 0);
        if ($isArrangerView) {
            $filterOrg = (int) ($work['org_id'] ?? $filterOrg);
        }
        $filterStatus = trim((string) ($_GET['status'] ?? ''));
        $filterWhen = trim((string) ($_GET['when'] ?? ''));
        $defaultSeasonScope = $isArrangerView ? 'all' : 'selected';
        $filterSeason = trim((string) ($_GET['season_scope'] ?? $defaultSeasonScope));
        if ($filterSeason !== 'all' && $filterSeason !== 'selected') {
            $filterSeason = $defaultSeasonScope;
        }

        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $seasonBySeries = (new \App\Service\CupSeasonResolver())->seasonLabelsBySeriesId(
            $hierarchy['roots'] ?? [],
            $hierarchy['children'] ?? [],
        );

        $now = time();
        $filtered = [];
        foreach ($events as $event) {
            if ($filterSeason !== 'all' && $seasonSeriesIds !== []) {
                $sid = (int) ($event['series_id'] ?? 0);
                if (!in_array($sid, $seasonSeriesIds, true)) {
                    continue;
                }
            }
            if ($filterOrg > 0 && (int) ($event['owner_org_id'] ?? 0) !== $filterOrg) {
                continue;
            }
            if ($filterStatus !== '' && (string) ($event['status'] ?? '') !== $filterStatus) {
                continue;
            }
            $starts = strtotime((string) ($event['starts_at'] ?? '')) ?: null;
            if ($filterWhen === 'upcoming' && $starts !== null && $starts < $now) {
                continue;
            }
            if ($filterWhen === 'past' && ($starts === null || $starts >= $now)) {
                continue;
            }
            $seriesId = (int) ($event['series_id'] ?? 0);
            $event['season_name'] = $seasonBySeries[$seriesId]
                ?? trim((string) ($event['series_name'] ?? $event['season_label'] ?? ''));
            $filtered[] = $event;
        }

        $organizers = [];
        foreach ($events as $event) {
            $oid = (int) ($event['owner_org_id'] ?? 0);
            if ($oid > 0) {
                $organizers[$oid] = (string) ($event['owner_org_name'] ?? ('#' . $oid));
            }
        }
        asort($organizers);

        $labels = $services->labels->resolveForSpace($space);
        $arrangerName = '';
        $seasonBlocks = [];
        if ($isArrangerView) {
            $arrangerName = (string) ($work['detail'] ?? '');
            $arrangerOrgId = (int) ($work['org_id'] ?? 0);
            $canCreateEvent = $arrangerOrgId > 0
                && $services->eventPolicy->canCreate($personId, $arrangerOrgId, $arrangerOrgId);
            $resolver = new \App\Service\CupSeasonResolver();
            $roots = $hierarchy['roots'] ?? [];
            $children = $hierarchy['children'] ?? [];

            // Alltid slå sammen godkjente seriearrangør-sesonger (API-hierarki kan være tomt/ufullstendig).
            if ($arrangerOrgId > 0) {
                $rootById = [];
                foreach ($roots as $existingRoot) {
                    $eid = (int) ($existingRoot['series_id'] ?? 0);
                    if ($eid > 0) {
                        $rootById[$eid] = $existingRoot;
                    }
                }
                foreach ($services->spaceParticipation->listOrganizerRootSeriesInSpace($arrangerOrgId, $spaceId) as $root) {
                    $rid = (int) ($root['series_id'] ?? 0);
                    if ($rid <= 0) {
                        continue;
                    }
                    if (!isset($rootById[$rid])) {
                        $rootById[$rid] = $root;
                    } else {
                        // Behold structure_type fra DB-fallback hvis API mangler det
                        if (($rootById[$rid]['structure_type'] ?? '') === ''
                            && ($root['structure_type'] ?? '') !== '') {
                            $rootById[$rid]['structure_type'] = $root['structure_type'];
                        }
                    }
                    if (!isset($children[$rid])) {
                        $children[$rid] = [];
                    }
                }
                $roots = array_values($rootById);
            }

            // Fyll inn runder fra DB når hierarki mangler barn.
            foreach ($roots as $root) {
                $rid = (int) ($root['series_id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                if (($children[$rid] ?? []) !== []) {
                    continue;
                }
                $dbChildren = $services->spaceParticipation->listChildSeriesByParentId($rid);
                if ($dbChildren !== []) {
                    $children[$rid] = $dbChildren;
                }
            }

            // Arrangør trenger ikke global sesong — vis aktuelle sesonger bolkvis (rundevis når aktuelt).
            foreach ($roots as $root) {
                $rootId = (int) ($root['series_id'] ?? 0);
                if ($rootId <= 0) {
                    continue;
                }
                $label = trim((string) ($root['name'] ?? $root['season_label'] ?? ''));
                if ($label === '') {
                    $label = 'Sesong #' . $rootId;
                }
                $seriesIds = $resolver->collectSeriesIds($root, $children);
                $childRounds = $children[$rootId] ?? [];
                $structure = (string) ($root['structure_type'] ?? '');
                $hasRoundChildren = $childRounds !== [];

                $blockEvents = [];
                foreach ($filtered as $event) {
                    if (in_array((int) ($event['series_id'] ?? 0), $seriesIds, true)) {
                        $blockEvents[] = $event;
                    }
                }

                if ($hasRoundChildren) {
                    $rounds = [];
                    $assignedEventIds = [];
                    foreach ($childRounds as $child) {
                        $roundId = (int) ($child['series_id'] ?? 0);
                        if ($roundId <= 0) {
                            continue;
                        }
                        $roundLabel = trim((string) ($child['name'] ?? $child['season_label'] ?? ''));
                        if ($roundLabel === '') {
                            $roundLabel = 'Runde #' . $roundId;
                        }
                        $roundEvents = [];
                        foreach ($blockEvents as $event) {
                            if ((int) ($event['series_id'] ?? 0) === $roundId) {
                                $roundEvents[] = $event;
                                $eid = (int) ($event['event_id'] ?? 0);
                                if ($eid > 0) {
                                    $assignedEventIds[$eid] = true;
                                }
                            }
                        }
                        $roundCreateHref = null;
                        if ($canCreateEvent) {
                            $roundCreateHref = PortalPaths::sesongStevneNew($roundId);
                        }
                        $rounds[] = [
                            'series_id' => $roundId,
                            'label' => $roundLabel,
                            'events' => $roundEvents,
                            'create_href' => $roundCreateHref,
                        ];
                    }

                    $orphanInSeason = [];
                    foreach ($blockEvents as $event) {
                        $eid = (int) ($event['event_id'] ?? 0);
                        if ($eid > 0 && !isset($assignedEventIds[$eid])) {
                            $orphanInSeason[] = $event;
                        }
                    }
                    if ($orphanInSeason !== []) {
                        $rounds[] = [
                            'series_id' => 0,
                            'label' => 'Uten runde',
                            'events' => $orphanInSeason,
                            'create_href' => null,
                        ];
                    }

                    $seasonBlocks[] = [
                        'series_id' => $rootId,
                        'label' => $label,
                        'events' => [],
                        'rounds' => $rounds,
                        'create_href' => null,
                        'create_batch_href' => $canCreateEvent
                            ? PortalPaths::sesongStevnerBatch($rootId)
                            : null,
                    ];
                    continue;
                }

                // Sesong uten runder (structure_type=events eller tom): flat listing.
                $createHref = null;
                if ($canCreateEvent
                    && $services->spaceParticipation->orgIsSeriesOrganizer($arrangerOrgId, $rootId)) {
                    $createHref = PortalPaths::sesongStevneNew($rootId);
                }
                $seasonBlocks[] = [
                    'series_id' => $rootId,
                    'label' => $label,
                    'events' => $blockEvents,
                    'rounds' => [],
                    'create_href' => $createHref,
                    'create_batch_href' => null,
                    'empty_hint' => ($structure === 'rounds' && $canCreateEvent)
                        ? 'Sesongen er satt opp med rundestruktur, men har ingen runder ennå. Du kan likevel opprette stevne direkte i sesongen.'
                        : null,
                ];
            }

            // Stevner uten kjent sesongrot (skal normalt ikke skje)
            $knownEventIds = [];
            foreach ($seasonBlocks as $block) {
                foreach ($block['events'] as $event) {
                    $knownEventIds[(int) ($event['event_id'] ?? 0)] = true;
                }
                foreach ($block['rounds'] ?? [] as $round) {
                    foreach ($round['events'] ?? [] as $event) {
                        $knownEventIds[(int) ($event['event_id'] ?? 0)] = true;
                    }
                }
            }
            $orphanEvents = [];
            foreach ($filtered as $event) {
                $eid = (int) ($event['event_id'] ?? 0);
                if ($eid > 0 && !isset($knownEventIds[$eid])) {
                    $orphanEvents[] = $event;
                }
            }
            if ($orphanEvents !== []) {
                $seasonBlocks[] = [
                    'series_id' => 0,
                    'label' => 'Andre stevner',
                    'events' => $orphanEvents,
                    'rounds' => [],
                    'create_href' => null,
                    'create_batch_href' => null,
                ];
            }
        }

        return PortalV3View::render('events/space-index', [
            'space' => $space,
            'events' => $filtered,
            'labels' => $labels,
            'organizers' => $organizers,
            'filter_organizer_id' => $filterOrg,
            'filter_status' => $filterStatus,
            'filter_when' => $filterWhen,
            'filter_season_scope' => $filterSeason,
            'season_label' => (string) ($bound['season_label'] ?? ''),
            'cup_access' => $menuAccess,
            'is_arranger_view' => $isArrangerView,
            'arranger_name' => $arrangerName,
            'season_blocks' => $seasonBlocks,
        ], $labels->plural('event'));
    }
}
