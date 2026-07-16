<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\V3\V3LoginController;
use App\Service\BackendApiClient;
use App\Support\Auth;
use App\Support\Config;
use App\Support\PortalCupBrand;
use App\Support\PortalProfile;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\Response;
use App\Support\Session;

final class LoginController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function showForm(): array
    {
        if (PortalV3::isEnabled()) {
            if (PortalV3Auth::check()) {
                return Response::redirect(PortalPaths::oversikt());
            }

            return Response::redirect(PortalPaths::login());
        }

        if (Auth::check()) {
            Auth::refreshFromBackend();

            return Response::redirect(Auth::hasOrganizerAccess() ? '/' : '/bli-arrangor');
        }

        $flash = Session::pullFlash();
        $error = is_array($flash) ? (string) ($flash['message'] ?? '') : '';

        return Response::view('arrangor/login', $this->loginViewData($error));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function submit(): array
    {
        if (PortalV3::isEnabled()) {
            return (new V3LoginController())->submit();
        }

        if (Auth::check()) {
            return Response::redirect('/');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            return Response::view('arrangor/login', $this->loginViewData('E-post og passord er påkrevd.'), 422);
        }

        $client = new BackendApiClient();
        $result = $client->participantLogin($email, $password);

        if (!($result['ok'] ?? false)) {
            $message = (string) ($result['error'] ?? 'Innlogging feilet.');
            if (str_contains(strtolower($message), 'could not reach backend')
                || str_contains(strtolower($message), 'backend request failed')) {
                $message = 'Kunne ikke nå backend API. Sjekk BACKEND_URL i .env.';
            }
            if (str_contains(strtolower($message), 'invalid email or password')) {
                $message = 'Ugyldig e-post eller passord.';
            }

            return Response::view('arrangor/login', $this->loginViewData($message), (int) ($result['status'] ?? 401));
        }

        $user = $result['data']['user'] ?? null;
        if (!is_array($user)) {
            return Response::view('arrangor/login', $this->loginViewData('Ugyldig svar fra backend.'), 502);
        }

        Session::setAuth($user);
        Auth::refreshFromBackend();

        return Response::redirect(Auth::hasOrganizerAccess() ? '/' : '/bli-arrangor');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function logout(): array
    {
        if (PortalV3::isEnabled()) {
            return (new V3LoginController())->logout();
        }

        $client = new BackendApiClient();
        $client->logout();
        Session::clear();

        return Response::redirect('/login');
    }

    /** @return array<string, mixed> */
    private function loginViewData(string $error = ''): array
    {
        $portal = PortalProfile::current();
        $brand = PortalCupBrand::resolve();
        if (($portal['cup_name'] ?? '') === '' && ($brand['name'] ?? '') !== '') {
            $portal['cup_name'] = (string) $brand['name'];
        }

        return [
            'title' => 'Logg inn',
            'error' => $error,
            'public_register_url' => trim((string) ($portal['register_url'] ?? ''))
                ?: (string) Config::get('app.public_register_url', ''),
            'portal' => $portal,
            'cup_brand' => $brand,
        ];
    }
}
