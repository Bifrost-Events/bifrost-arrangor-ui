<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\PortalBoundCup;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Service\PortalWorkContext;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3ContextController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function switchWorkArea(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $key = trim((string) ($_POST['work_key'] ?? $_GET['work_key'] ?? ''));
        $bound = (new PortalBoundCup($services))->resolve($personId);
        $space = $bound['space'] ?? null;
        if (!is_array($space)) {
            PortalV3Session::setFlash('error', 'Ingen aktiv cup.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        $work = new PortalWorkContext($services);
        $options = $work->options($space, $access, $personId);
        $selected = null;
        foreach ($options as $opt) {
            if (($opt['key'] ?? '') === $key) {
                $selected = $opt;
                break;
            }
        }
        if ($selected === null) {
            PortalV3Session::setFlash('error', 'Ugyldig arbeidsområde.');

            return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? $_GET['return'] ?? '')));
        }

        $work->apply($personId, (string) $selected['mode'], (int) $selected['org_id'], true);

        // Arrangør → rett til stevneliste; cupadmin → oversikt.
        if (($selected['mode'] ?? '') === PortalV3Session::WORK_MODE_ARRANGER) {
            return Response::redirect(PortalPaths::stevner() . '?season_scope=all');
        }

        return Response::redirect(PortalPaths::oversikt());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function switchSeason(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $seriesId = (int) ($_POST['season_series_id'] ?? $_GET['season_series_id'] ?? 0);
        $spaceId = (int) ($services->organizationContext->activeSpaceId() ?? 0);

        if ($spaceId <= 0 || $seriesId <= 0) {
            PortalV3Session::setFlash('error', 'Kunne ikke bytte sesong.');

            return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? $_GET['return'] ?? '')));
        }

        $space = $services->eventSpaces->findAccessibleForPerson($personId, $spaceId);
        if ($space === null) {
            PortalV3Session::setFlash('error', 'Ingen tilgang til cupen.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId()
            ?? $space['owner_org_id']
            ?? 0);
        if ($orgId <= 0) {
            PortalV3Session::setFlash('error', 'Kunne ikke bytte sesong.');

            return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? $_GET['return'] ?? '')));
        }

        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $valid = false;
        foreach ($hierarchy['roots'] ?? [] as $root) {
            if ((int) ($root['series_id'] ?? 0) === $seriesId) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            PortalV3Session::setFlash('error', 'Ugyldig sesong for denne cupen.');

            return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? $_GET['return'] ?? '')));
        }

        PortalV3Session::setSeasonSeriesId($seriesId);

        return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? $_GET['return'] ?? '')));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function selectOrganization(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $orgs = $services->organizationContext->administrableOrganizations($personId);

        return PortalV3View::render('context/organization', [
            'organizations' => $orgs,
            'active_organization_id' => $services->organizationContext->activeOrganizationId(),
        ], 'Organisasjoner');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function submitOrganization(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($_POST['organization_id'] ?? 0);

        // Behold aktiv cup — org-bytte er ofte bare «hvem jeg handler som» innen cupen.
        if (!$services->organizationContext->setActiveOrganization($orgId, $personId, false)) {
            PortalV3Session::setFlash('error', 'Ugyldig organisasjon.');

            return Response::redirect(PortalPaths::kontekstOrganisasjon());
        }

        return Response::redirect($this->safeReturnUrl((string) ($_POST['return'] ?? '')));
    }

    /** Hurtigbytte fra sidepanel (GET). */
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function switchOrganization(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $services = new PortalV3Services();
        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($_GET['organization_id'] ?? 0);

        if (!$services->organizationContext->setActiveOrganization($orgId, $personId, false)) {
            PortalV3Session::setFlash('error', 'Ugyldig organisasjon.');

            return Response::redirect(PortalPaths::kontekstOrganisasjon());
        }

        return Response::redirect($this->safeReturnUrl((string) ($_GET['return'] ?? '')));
    }

    private function safeReturnUrl(string $return): string
    {
        $return = trim($return);
        if ($return === '' || !PortalPaths::isPortalPath(parse_url($return, PHP_URL_PATH) ?: $return)) {
            if (PortalV3Session::getSpaceId()) {
                return PortalPaths::cup();
            }

            return PortalPaths::cups();
        }

        return $return;
    }
}
