<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\PortalBoundCup;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Service\PortalWorkContext;

final class PortalV3View
{
    /**
     * @param array<string, mixed> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function render(string $partial, array $data = [], string $title = 'Arrangørportal'): array
    {
        $services = new PortalV3Services();
        $orgContext = $services->organizationContext;
        $orgContext->syncFromRequest();

        $user = PortalV3Auth::user();
        $personId = PortalV3Auth::personId() ?? 0;
        $activeOrg = $personId > 0 ? $orgContext->resolveActiveOrganization($personId) : null;
        $allOrgs = $personId > 0 ? $orgContext->administrableOrganizations($personId) : [];
        $orgId = (int) ($activeOrg['org_id'] ?? 0);

        $bound = $personId > 0
            ? (new PortalBoundCup($services))->resolve($personId)
            : [
                'space' => null,
                'domain_bound' => false,
                'season' => null,
                'season_label' => '',
                'access' => [],
                'domain' => null,
            ];

        $activeSpace = is_array($data['space'] ?? null) ? $data['space'] : $bound['space'];
        $spaceId = $activeSpace !== null ? (int) ($activeSpace['space_id'] ?? 0) : null;
        $domainBound = (bool) ($bound['domain_bound'] ?? false);
        $domain = $bound['domain'] ?? $services->domainContext->resolveFromRequest();
        $access = is_array($bound['access'] ?? null) ? $bound['access'] : [];
        if ($activeSpace !== null && $access === [] && $personId > 0) {
            $access = (new PortalCupAccess($services))->forSpace($personId, $activeSpace);
        }

        $labels = $activeSpace !== null
            ? $services->labels->resolveForSpace($activeSpace)
            : $services->labels->resolveForSpace(null);

        $appKey = (string) ($domain['application_key'] ?? $activeSpace['application_key'] ?? '');
        $brand = PortalCupBrand::resolve($appKey !== '' ? $appKey : null);

        // Sidefelt: cupansvarlig + arrangørorg'er brukeren administrerer i aktiv cup (dedupe).
        $orgs = self::sidebarOrganizations($allOrgs, $activeSpace, $access);

        $work = [
            'mode' => PortalV3Session::WORK_MODE_ARRANGER,
            'org_id' => $orgId,
            'label' => '',
            'detail' => '',
            'key' => '',
            'options' => [],
        ];
        if ($personId > 0 && $activeSpace !== null) {
            $work = (new PortalWorkContext($services))->resolve($personId, $activeSpace, $access);
            // Oppdater aktiv org etter resolve (kan ha synket session).
            $activeOrg = $orgContext->resolveActiveOrganization($personId);
            $orgId = (int) ($activeOrg['org_id'] ?? $work['org_id'] ?? 0);
        }
        $menuAccess = (new PortalWorkContext($services))->menuAccess($access, $work);

        $seasonLabel = (string) ($bound['season_label'] ?? '');
        if ($seasonLabel === '' && is_array($bound['season'] ?? null)) {
            $seasonLabel = (string) (($bound['season']['name'] ?? $bound['season']['season_label'] ?? ''));
        }
        $seasonOptions = is_array($bound['season_options'] ?? null) ? $bound['season_options'] : [];
        $seasonSeriesId = isset($bound['season_series_id']) ? (int) $bound['season_series_id'] : 0;
        $activeSeason = is_array($bound['season'] ?? null) ? $bound['season'] : null;

        $currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        $menu = $personId > 0
            ? PortalV3Menu::build(
                $labels,
                $activeSpace,
                $currentPath,
                $domainBound,
                $menuAccess,
                $seasonSeriesId,
            )
            : [];
        $accountLinks = $personId > 0 ? PortalV3Menu::accountLinks() : [];

        $layout = [
            'title' => $title,
            'user' => $user,
            'flash' => PortalV3Session::pullFlash(),
            'active_org' => $activeOrg,
            'organizations' => $orgs,
            'active_space_id' => $spaceId,
            'active_space' => $activeSpace,
            'labels' => $labels,
            'route_prefix' => PortalPaths::routePrefix(),
            'pp' => PortalPaths::class,
            'menu' => $menu,
            'account_links' => $accountLinks,
            'domain_bound' => $domainBound,
            'cup_brand' => $brand,
            'season_label' => $seasonLabel,
            'season_options' => $seasonOptions,
            'season_series_id' => $seasonSeriesId > 0 ? $seasonSeriesId : null,
            'active_season' => $activeSeason,
            'cup_access' => $access,
            'work_context' => $work,
            'menu_access' => $menuAccess,
            'cup_owner_org_id' => (int) ($activeSpace['owner_org_id'] ?? 0),
        ];

        $data = array_merge($layout, $data, [
            'active_space' => $activeSpace,
            'labels' => $labels,
            'cup_access' => $access,
            'work_context' => $work,
            'menu_access' => $menuAccess,
            'season_label' => $seasonLabel,
            'season' => $data['season'] ?? $activeSeason,
            'domain_application' => $domain,
        ]);

        $content = Response::partial('portal-v3/' . $partial, $data);
        $layout['content'] = $content;

        return Response::view('portal-v3/layout', $layout);
    }

    /**
     * @param list<array<string, mixed>> $allOrgs
     * @param array<string, mixed>|null $space
     * @param array<string, mixed> $access
     * @return list<array<string, mixed>>
     */
    private static function sidebarOrganizations(array $allOrgs, ?array $space, array $access): array
    {
        $byId = [];
        foreach ($allOrgs as $org) {
            $id = (int) ($org['org_id'] ?? 0);
            if ($id > 0 && !isset($byId[$id])) {
                $byId[$id] = $org;
            }
        }

        if ($space === null) {
            return array_values($byId);
        }

        $cupOwner = (int) ($space['owner_org_id'] ?? 0);
        $relevantIds = [];
        if ($cupOwner > 0) {
            $relevantIds[] = $cupOwner;
        }
        foreach ($access['arranger_org_ids'] ?? [] as $id) {
            $relevantIds[] = (int) $id;
        }
        // Cupadmin: vis også orgs de adminer som faktisk er host i cupen
        if ($access['can_manage_cup'] ?? false) {
            foreach ($access['admin_org_ids'] ?? [] as $id) {
                // kun cup-eier i sidefelt for cupadmin — arrangører ligger på Arrangører-siden
                if ((int) $id === $cupOwner) {
                    $relevantIds[] = (int) $id;
                }
            }
        } else {
            foreach ($access['admin_org_ids'] ?? [] as $id) {
                if (in_array((int) $id, $access['arranger_org_ids'] ?? [], true)) {
                    $relevantIds[] = (int) $id;
                }
            }
        }

        $relevantIds = array_values(array_unique(array_filter($relevantIds)));
        $filtered = [];
        foreach ($relevantIds as $id) {
            if (isset($byId[$id])) {
                $row = $byId[$id];
                $row['is_cup_owner'] = $id === $cupOwner;
                $filtered[] = $row;
            }
        }

        return $filtered !== [] ? $filtered : array_values($byId);
    }
}
