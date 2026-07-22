<?php

declare(strict_types=1);

use App\Controller\V3\V3ArrangerController;
use App\Controller\V3\V3ContextController;
use App\Controller\V3\V3DashboardController;
use App\Controller\V3\V3EventController;
use App\Controller\V3\V3JaktfeltController;
use App\Controller\V3\V3OnboardingController;
use App\Controller\V3\V3RegistrationController;
use App\Controller\V3\V3LoginController;
use App\Controller\V3\V3PasswordController;
use App\Controller\V3\V3SeriesController;
use App\Controller\V3\V3SpaceController;
use App\Support\PortalActiveSpace;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\Response;
use App\Support\Router;
use App\Service\PortalV3Services;

return function (Router $router): void {
    if (!PortalV3::isEnabled()) {
        return;
    }

    $login = new V3LoginController();
    $password = new V3PasswordController();
    $dashboard = new V3DashboardController();
    $context = new V3ContextController();
    $spaces = new V3SpaceController();
    $series = new V3SeriesController();
    $events = new V3EventController();
    $registrations = new V3RegistrationController();
    $jaktfelt = new V3JaktfeltController();
    $arrangers = new V3ArrangerController();
    $onboarding = new V3OnboardingController();

    $withSpace = static function (callable $handler): callable {
        return static function (mixed ...$extra) use ($handler) {
            $services = new PortalV3Services();
            if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
                return $redirect;
            }
            [$spaceId, $redirect] = PortalActiveSpace::requireId($services);
            if ($redirect !== null) {
                return $redirect;
            }

            return $handler((int) $spaceId, ...$extra);
        };
    };

    // —— Primære brukerrettede URL-er ——
    $router->get('/', static function () {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        return Response::redirect(PortalPaths::login());
    });
    $router->get(PortalPaths::oversikt(), fn () => $dashboard->index());

    $router->get(PortalPaths::login(), fn () => $login->showForm());
    $router->post(PortalPaths::login(), fn () => $login->submit());
    $router->post(PortalPaths::logout(), fn () => $login->logout());
    $router->get(PortalPaths::glemtPassord(), fn () => $password->forgotForm());
    $router->post(PortalPaths::glemtPassord(), fn () => $password->forgotSubmit());
    $router->get(PortalPaths::tilbakestillPassord(), fn () => $password->resetForm());
    $router->post(PortalPaths::tilbakestillPassord(), fn () => $password->resetSubmit());

    $router->get(PortalPaths::kontekstOrganisasjon(), fn () => $context->selectOrganization());
    $router->get(PortalPaths::kontekstOrganisasjonBytt(), fn () => $context->switchOrganization());
    $router->post(PortalPaths::kontekstOrganisasjon(), fn () => $context->submitOrganization());
    $router->post(PortalPaths::kontekstSesong(), fn () => $context->switchSeason());
    $router->get(PortalPaths::kontekstArbeidsomrade(), fn () => $context->switchWorkArea());
    $router->post(PortalPaths::kontekstArbeidsomrade(), fn () => $context->switchWorkArea());

    $router->get(PortalPaths::cups(), fn () => $spaces->index());
    $router->get(PortalPaths::cup(), $withSpace(fn (int $spaceId) => $spaces->show($spaceId)));
    $router->get(PortalPaths::sesonger(), $withSpace(fn (int $spaceId) => $spaces->show($spaceId)));
    $router->get(PortalPaths::cupEdit(), $withSpace(fn (int $spaceId) => $spaces->editForm($spaceId)));
    $router->post(PortalPaths::cupEdit(), $withSpace(fn (int $spaceId) => $spaces->editSubmit($spaceId)));

    $router->get(PortalPaths::stevner(), $withSpace(fn (int $spaceId) => $spaces->listEvents($spaceId)));
    $router->get(PortalPaths::arrangorer(), $withSpace(fn (int $spaceId) => $arrangers->index($spaceId)));
    $router->get(PortalPaths::arrangorNyttStevne(), $withSpace(fn (int $spaceId) => $arrangers->createEventForm($spaceId)));
    $router->post(PortalPaths::arrangorNyttStevne(), $withSpace(fn (int $spaceId) => $arrangers->createEventSubmit($spaceId)));
    $router->get('/arrangorer/{orgId}', $withSpace(fn (int $spaceId, int $orgId) => $arrangers->show($spaceId, $orgId)));

    $router->get(PortalPaths::sesongNew(), $withSpace(fn (int $spaceId) => $series->createRootForm($spaceId)));
    $router->post(PortalPaths::sesonger(), $withSpace(fn (int $spaceId) => $series->createRootSubmit($spaceId)));
    $router->get('/sesonger/{parentId}/undersoner/ny', $withSpace(fn (int $spaceId, int $parentId) => $series->createChildForm($spaceId, $parentId)));
    $router->post('/sesonger/{parentId}/undersoner', $withSpace(fn (int $spaceId, int $parentId) => $series->createChildSubmit($spaceId, $parentId)));
    $router->post('/sesonger/{seriesId}/runder', fn (int $seriesId) => $series->roundsMatrixSubmit($seriesId));
    $router->post('/sesonger/{seriesId}/runder/opprett', fn (int $seriesId) => $series->roundsBatchCreate($seriesId));
    $router->get('/sesonger/{seriesId}/rediger', fn (int $seriesId) => $series->editForm($seriesId));
    $router->post('/sesonger/{seriesId}/rediger', fn (int $seriesId) => $series->editSubmit($seriesId));
    $router->get('/sesonger/{seriesId}/struktur', fn (int $seriesId) => $series->structureForm($seriesId));
    $router->post('/sesonger/{seriesId}/struktur', fn (int $seriesId) => $series->structureSubmit($seriesId));
    $router->get('/sesonger/{seriesId}/sammenlagt', fn (int $seriesId) => $series->scoringForm($seriesId));
    $router->post('/sesonger/{seriesId}/sammenlagt', fn (int $seriesId) => $series->scoringSubmit($seriesId));
    $router->post('/sesonger/{seriesId}/arkiver', fn (int $seriesId) => $series->archiveSubmit($seriesId));
    $router->post('/sesonger/{seriesId}/cup-standings', fn (int $seriesId) => $series->cupStandingsSubmit($seriesId));

    $router->get('/sesonger/{seriesId}/stevner', $withSpace(fn (int $spaceId, int $seriesId) => $events->index($spaceId, $seriesId)));
    $router->get('/sesonger/{seriesId}/stevner/ny', $withSpace(fn (int $spaceId, int $seriesId) => $events->createForm($spaceId, $seriesId)));
    $router->get('/sesonger/{seriesId}/stevner/batch', $withSpace(fn (int $spaceId, int $seriesId) => $events->batchCreateForm($spaceId, $seriesId)));
    $router->post('/sesonger/{seriesId}/stevner/batch', $withSpace(fn (int $spaceId, int $seriesId) => $events->batchCreateSubmit($spaceId, $seriesId)));
    $router->post('/sesonger/{seriesId}/stevner', $withSpace(fn (int $spaceId, int $seriesId) => $events->createSubmit($spaceId, $seriesId)));

    $router->get('/stevner/{eventId}', fn (int $eventId) => $events->editForm($eventId));
    $router->post('/stevner/{eventId}', fn (int $eventId) => $events->updateSubmit($eventId));
    $router->post('/stevner/{eventId}/arkiver', fn (int $eventId) => $events->archiveSubmit($eventId));
    $router->get('/stevner/{eventId}/pameldinger', fn (int $eventId) => $registrations->index($eventId));
    $router->get('/stevner/{eventId}/pameldinger/ny', fn (int $eventId) => $registrations->createForm($eventId));
    $router->post('/stevner/{eventId}/pameldinger', fn (int $eventId) => $registrations->createSubmit($eventId));
    $router->get('/stevner/{eventId}/pameldinger/export', fn (int $eventId) => $registrations->export($eventId));
    $router->get('/stevner/{eventId}/pameldinger/{registrationId}', fn (int $eventId, int $registrationId) => $registrations->show($eventId, $registrationId));
    $router->post('/stevner/{eventId}/pameldinger/{registrationId}', fn (int $eventId, int $registrationId) => $registrations->updateSubmit($eventId, $registrationId));

    $router->get('/stevner/{eventId}/jaktfelt', fn (int $eventId) => $jaktfelt->grid($eventId));
    $router->post('/stevner/{eventId}/jaktfelt', fn (int $eventId) => $jaktfelt->generateSubmit($eventId));
    $router->post('/stevner/{eventId}/jaktfelt/flytt', fn (int $eventId) => $jaktfelt->moveSubmit($eventId));
    $router->post('/stevner/{eventId}/jaktfelt/pamelding', fn (int $eventId) => $jaktfelt->registerSubmit($eventId));

    // —— Onboarding / org self-service ——
    // /kom-i-gang er åpen for gjester (kontoopprettelse) og innloggede
    $router->get(PortalPaths::komIGang(), fn () => $onboarding->getStarted());
    $router->post(PortalPaths::komIGang(), fn () => $onboarding->registerSubmit());
    $router->get(PortalPaths::mineOrganisasjoner(), fn () => $onboarding->listOrganizations());
    $router->get(PortalPaths::mineOrganisasjonerNy(), fn () => $onboarding->createOrgForm());
    $router->post(PortalPaths::mineOrganisasjonerNy(), fn () => $onboarding->createOrgSubmit());
    $router->get(PortalPaths::arrangorSoknader(), fn () => $onboarding->listApplications());
    $router->get(PortalPaths::arrangorSoknadNy(), fn () => $onboarding->newApplicationForm());
    $router->post(PortalPaths::arrangorSoknadNy(), fn () => $onboarding->createApplicationSubmit());
    $router->get('/arrangor-soknader/{id}', fn (int $id) => $onboarding->showApplication($id));
    $router->post('/arrangor-soknader/{id}/send-inn', fn (int $id) => $onboarding->submitApplication($id));
    $router->post('/arrangor-soknader/{id}/trekk', fn (int $id) => $onboarding->withdrawApplication($id));

    // —— Serieeier: behandle søknader ——
    $router->get('/sesonger/{seriesId}/arrangor-soknader', fn (int $seriesId) => $onboarding->listSeriesApplications($seriesId));
    $router->post('/sesonger/{seriesId}/arrangor-soknader/innstillinger', fn (int $seriesId) => $onboarding->updateSeriesOnboardingSettings($seriesId));
    $router->get('/sesonger/{seriesId}/arrangor-soknader/{id}', fn (int $seriesId, int $id) => $onboarding->showSeriesApplication($seriesId, $id));
    $router->post('/sesonger/{seriesId}/arrangor-soknader/{id}/godkjenn', fn (int $seriesId, int $id) => $onboarding->approve($seriesId, $id));
    $router->post('/sesonger/{seriesId}/arrangor-soknader/{id}/avvis', fn (int $seriesId, int $id) => $onboarding->reject($seriesId, $id));
    $router->post('/sesonger/{seriesId}/arrangor-soknader/{id}/under-behandling', fn (int $seriesId, int $id) => $onboarding->setUnderReview($seriesId, $id));

    // —— Legacy /portal-v3/* → nye paths (GET redirects; POST aliases uten ekstra logikk) ——
    $legacy = PortalPaths::LEGACY_PREFIX;

    $redirectSetSpace = static function (int $spaceId, string $to) {
        if ($spaceId > 0) {
            PortalV3Session::setSpaceId($spaceId);
        }

        return Response::redirect($to, 301);
    };

    $router->get($legacy, fn () => Response::redirect(PortalPaths::oversikt(), 301));
    $router->get($legacy . '/login', fn () => Response::redirect(PortalPaths::login(), 301));
    $router->post($legacy . '/login', fn () => $login->submit());
    $router->post($legacy . '/logout', fn () => $login->logout());

    $router->get($legacy . '/context/organization', fn () => Response::redirect(PortalPaths::kontekstOrganisasjon(), 301));
    $router->get($legacy . '/context/organization/switch', fn () => $context->switchOrganization());
    $router->post($legacy . '/context/organization', fn () => $context->submitOrganization());
    $router->post($legacy . '/context/season', fn () => $context->switchSeason());

    $router->get($legacy . '/spaces', fn () => Response::redirect(PortalPaths::cups(), 301));
    $router->get($legacy . '/spaces/{spaceId}', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::cup()));
    $router->get($legacy . '/spaces/{spaceId}/edit', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::cupEdit()));
    $router->post($legacy . '/spaces/{spaceId}/edit', fn (int $spaceId) => $spaces->editSubmit($spaceId));
    $router->get($legacy . '/spaces/{spaceId}/events', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::stevner()));
    $router->get($legacy . '/spaces/{spaceId}/arrangers', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::arrangorer()));
    $router->get($legacy . '/spaces/{spaceId}/arrangers/new-event', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::arrangorNyttStevne()));
    $router->post($legacy . '/spaces/{spaceId}/arrangers/new-event', fn (int $spaceId) => $arrangers->createEventSubmit($spaceId));
    $router->get($legacy . '/spaces/{spaceId}/arrangers/{orgId}', fn (int $spaceId, int $orgId) => $redirectSetSpace($spaceId, PortalPaths::arrangor($orgId)));

    $router->get($legacy . '/spaces/{spaceId}/series/new', fn (int $spaceId) => $redirectSetSpace($spaceId, PortalPaths::sesongNew()));
    $router->post($legacy . '/spaces/{spaceId}/series', fn (int $spaceId) => $series->createRootSubmit($spaceId));
    $router->get($legacy . '/spaces/{spaceId}/series/{parentId}/children/new', fn (int $spaceId, int $parentId) => $redirectSetSpace($spaceId, PortalPaths::sesongChildNew($parentId)));
    $router->post($legacy . '/spaces/{spaceId}/series/{parentId}/children', fn (int $spaceId, int $parentId) => $series->createChildSubmit($spaceId, $parentId));
    $router->get($legacy . '/series/{seriesId}/edit', fn (int $seriesId) => Response::redirect(PortalPaths::sesongEdit($seriesId), 301));
    $router->post($legacy . '/series/{seriesId}/edit', fn (int $seriesId) => $series->editSubmit($seriesId));
    $router->post($legacy . '/series/{seriesId}/archive', fn (int $seriesId) => $series->archiveSubmit($seriesId));
    $router->post($legacy . '/series/{seriesId}/cup-standings', fn (int $seriesId) => $series->cupStandingsSubmit($seriesId));

    $router->get($legacy . '/spaces/{spaceId}/series/{seriesId}/events', fn (int $spaceId, int $seriesId) => $redirectSetSpace($spaceId, PortalPaths::sesongStevner($seriesId)));
    $router->get($legacy . '/spaces/{spaceId}/series/{seriesId}/events/new', fn (int $spaceId, int $seriesId) => $redirectSetSpace($spaceId, PortalPaths::sesongStevneNew($seriesId)));
    $router->post($legacy . '/spaces/{spaceId}/series/{seriesId}/events', fn (int $spaceId, int $seriesId) => $events->createSubmit($spaceId, $seriesId));

    $router->get($legacy . '/events/{eventId}/edit', fn (int $eventId) => Response::redirect(PortalPaths::stevne($eventId), 301));
    $router->post($legacy . '/events/{eventId}/edit', fn (int $eventId) => $events->updateSubmit($eventId));
    $router->post($legacy . '/events/{eventId}/archive', fn (int $eventId) => $events->archiveSubmit($eventId));
};
