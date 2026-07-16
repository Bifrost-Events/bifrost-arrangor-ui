<?php

declare(strict_types=1);

use App\Controller\V3\V3ArrangerController;
use App\Controller\V3\V3ContextController;
use App\Controller\V3\V3DashboardController;
use App\Controller\V3\V3EventController;
use App\Controller\V3\V3LoginController;
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
    $dashboard = new V3DashboardController();
    $context = new V3ContextController();
    $spaces = new V3SpaceController();
    $series = new V3SeriesController();
    $events = new V3EventController();
    $arrangers = new V3ArrangerController();

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
    $router->post('/sesonger/{seriesId}/stevner', $withSpace(fn (int $spaceId, int $seriesId) => $events->createSubmit($spaceId, $seriesId)));

    $router->get('/stevner/{eventId}', fn (int $eventId) => $events->editForm($eventId));
    $router->post('/stevner/{eventId}', fn (int $eventId) => $events->updateSubmit($eventId));
    $router->post('/stevner/{eventId}/arkiver', fn (int $eventId) => $events->archiveSubmit($eventId));

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
