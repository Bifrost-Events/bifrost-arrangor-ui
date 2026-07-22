<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\CupArrangerService;
use App\Service\PortalBoundCup;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Service\PortalWorkContext;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3DashboardController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function index(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $orgs = $services->organizationContext->administrableOrganizations($personId);
        if ($orgs === []) {
            return Response::redirect(PortalPaths::komIGang());
        }

        $bound = (new PortalBoundCup($services))->resolve($personId);
        $space = $bound['space'];

        if ($space === null) {
            if ($bound['domain_bound']) {
                return PortalV3View::render('no-access', [
                    'message' => 'Ingen cup tilgjengelig på dette domenet for din bruker.',
                ], 'Ingen cup');
            }

            return Response::redirect(PortalPaths::cups());
        }

        $spaceId = (int) ($space['space_id'] ?? 0);
        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        $workCtx = new PortalWorkContext($services);
        $work = $workCtx->resolve($personId, $space, $access);
        $menuAccess = $workCtx->menuAccess($access, $work);

        // Arrangørmodus: stevnelisten er hovedvinduet.
        if (($work['mode'] ?? '') === PortalV3Session::WORK_MODE_ARRANGER) {
            return Response::redirect(PortalPaths::stevner() . '?season_scope=all');
        }

        $listOrgId = $workCtx->listOrgId($space, $access, $work);
        $orgId = (int) ($work['org_id'] ?: $listOrgId);
        $labels = $services->labels->resolveForSpace($space);
        $events = $services->events->listForSpace($personId, $spaceId, $listOrgId);
        $events = $workCtx->filterEventsForWork($events, $menuAccess, $access);
        $seasonSeriesIds = is_array($bound['season_series_ids'] ?? null) ? $bound['season_series_ids'] : [];
        if ($seasonSeriesIds !== []) {
            $events = array_values(array_filter(
                $events,
                static fn (array $e): bool => in_array((int) ($e['series_id'] ?? 0), $seasonSeriesIds, true),
            ));
        }

        $now = time();
        $upcoming = [];
        foreach ($events as $event) {
            $starts = strtotime((string) ($event['starts_at'] ?? '')) ?: 0;
            if ($starts >= $now || ($event['starts_at'] ?? null) === null) {
                $upcoming[] = $event;
            }
        }
        usort($upcoming, static function (array $a, array $b): int {
            return strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? ''));
        });

        $arrangerPayload = ($menuAccess['can_view_arrangers'] ?? false)
            ? (new CupArrangerService($services))->listForSpace(
                $personId,
                $space,
                $orgId,
                $bound['season_series_id'] ?? null,
            )
            : ['arrangers' => [], 'season' => $bound['season']];
        $arrangers = $arrangerPayload['arrangers'];
        $missing = array_values(array_filter(
            $arrangers,
            static fn (array $a): bool => (bool) ($a['missing_season_event'] ?? false),
        ));

        $myOrgs = [];
        if (($work['mode'] ?? '') === PortalV3Session::WORK_MODE_ARRANGER) {
            foreach ($orgs as $org) {
                if ((int) ($org['org_id'] ?? 0) === (int) ($work['org_id'] ?? 0)) {
                    $myOrgs[] = $org;
                }
            }
        } else {
            foreach ($orgs as $org) {
                $oid = (int) ($org['org_id'] ?? 0);
                if (in_array($oid, $access['arranger_org_ids'] ?? [], true)
                    || (($access['is_cup_admin'] ?? false) && $oid === (int) ($space['owner_org_id'] ?? 0))) {
                    $myOrgs[] = $org;
                }
            }
        }

        return PortalV3View::render('dashboard', [
            'space' => $space,
            'labels' => $labels,
            'access' => $menuAccess,
            'season' => $bound['season'] ?? $arrangerPayload['season'] ?? null,
            'season_label' => $bound['season_label'],
            'event_count' => count($events),
            'upcoming_events' => array_slice($upcoming, 0, 8),
            'arranger_count' => count($arrangers),
            'missing_arrangers' => $missing,
            'my_organizations' => $myOrgs,
        ], 'Oversikt');
    }
}
