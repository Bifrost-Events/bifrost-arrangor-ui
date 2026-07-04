<?php

declare(strict_types=1);

use App\Controller\CompetitionsController;
use App\Controller\CompetitionsStevneAdminController;
use App\Controller\DashboardController;
use App\Controller\HealthController;
use App\Controller\InvitationController;
use App\Controller\LoginController;
use App\Controller\OnboardingController;
use App\Controller\OrganizationController;
use App\Controller\ParticipantsController;
use App\Support\ArrangorMenu;
use App\Support\ArrangorView;
use App\Support\Router;

return function (array $app): Router {
    $router = new Router();
    $login = new LoginController();
    $onboarding = new OnboardingController();
    $competitions = new CompetitionsController();
    $participants = new ParticipantsController();
    $organization = new OrganizationController();
    $invitation = new InvitationController();
    $stevneAdmin = new CompetitionsStevneAdminController();

    $router->get('/login', fn () => $login->showForm());
    $router->post('/login', fn () => $login->submit());
    $router->post('/logout', fn () => $login->logout());
    $router->get('/health', fn () => (new HealthController())());
    $router->get('/', fn () => (new DashboardController())());

    $router->get('/bli-arrangor', fn () => $onboarding->index());
    $router->post('/bli-arrangor/vilkar', fn () => $onboarding->acceptTerms());
    $router->get('/bli-arrangor/opprett', fn () => $onboarding->createForm());
    $router->post('/bli-arrangor/opprett', fn () => $onboarding->createSubmit());

    $router->get('/stevner', fn () => $competitions->index());
    $router->get('/stevner/ny', fn () => $competitions->createForm());
    $router->get('/stevner/stevneadmin', fn () => $stevneAdmin->index());
    $router->post('/stevner', fn () => $competitions->createSubmit());
    $router->get('/stevner/{id}/stevneadmin', fn (int $id) => $stevneAdmin->show($id));
    $router->get('/stevner/{id}/stevneadmin/sok-deltaker', fn (int $id) => $stevneAdmin->searchParticipants($id));
    $router->post('/stevner/{id}/stevneadmin/generer-lag', fn (int $id) => $stevneAdmin->generateSlots($id));
    $router->post('/stevner/{id}/stevneadmin/godkjenning', fn (int $id) => $stevneAdmin->approval($id));
    $router->post('/stevner/{id}/stevneadmin/lag/{slot}/lagre', fn (int $id, int $slot) => $stevneAdmin->saveSlot($id, $slot));
    $router->post('/stevner/{id}/stevneadmin/lag/{slot}/laas', fn (int $id, int $slot) => $stevneAdmin->lockSlot($id, $slot));
    $router->post('/stevner/{id}/stevneadmin/lag/{slot}/tilordne', fn (int $id, int $slot) => $stevneAdmin->assign($id, $slot));
    $router->post('/stevner/{id}/stevneadmin/lag/{slot}/fjern', fn (int $id, int $slot) => $stevneAdmin->remove($id, $slot));
    $router->post('/stevner/{id}/stevneadmin/reserver-lag', fn (int $id) => $stevneAdmin->reserveSlot($id));
    $router->post('/stevner/{id}/stevneadmin/reserver-skive', fn (int $id) => $stevneAdmin->reserveFigure($id));
    $router->get('/stevner/{id}', fn (int $id) => $competitions->editForm($id));
    $router->post('/stevner/{id}', fn (int $id) => $competitions->updateSubmit($id));

    $router->get('/deltakere', fn () => $participants->index());
    $router->post('/deltakere', fn () => $participants->createSubmit());

    $router->get('/min-organisasjon', fn () => $organization->profile());
    $router->get('/organisasjon/medlemmer', fn () => $organization->members());
    $router->post('/organisasjon/medlemmer/inviter', fn () => $organization->inviteSubmit());

    $router->get('/invitasjon/aksepter', fn () => $invitation->acceptForm());
    $router->post('/invitasjon/aksepter', fn () => $invitation->acceptSubmit());

    $implementedPaths = [
        '/',
        '/stevner',
        '/stevner/stevneadmin',
        '/deltakere',
        '/min-organisasjon',
        '/organisasjon/medlemmer',
    ];

    foreach (ArrangorMenu::allPages() as $page) {
        if (!is_array($page)) {
            continue;
        }
        $pageId = (string) ($page['id'] ?? '');
        $path = (string) ($page['path'] ?? '');
        if ($pageId === '' || $path === '' || $path === '/') {
            continue;
        }
        if (in_array($path, $implementedPaths, true)) {
            continue;
        }
        $router->get($path, static fn () => ArrangorView::render($pageId));
    }

    return $router;
};
