<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\PortalV3Services;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3LoginController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function showForm(): array
    {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        return PortalV3View::render('login', [], 'Logg inn');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function submit(): array
    {
        $services = new PortalV3Services();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = $services->auth->login($email, $password);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Innlogging feilet.'));

            return Response::redirect(PortalPaths::login());
        }

        return Response::redirect(PortalPaths::oversikt());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function logout(): array
    {
        (new PortalV3Services())->auth->logout();

        return Response::redirect(PortalPaths::login());
    }
}
