<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\AuthPasswordClient;
use App\Support\Config;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3PasswordController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function forgotForm(): array
    {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        return PortalV3View::render('auth/forgot-password', [
            'submitted' => false,
            'message' => '',
        ], 'Glemt passord');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function forgotSubmit(): array
    {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $resetUrl = $this->absoluteResetUrl();
        $result = (new AuthPasswordClient())->forgotPassword($email, $resetUrl);

        $message = (string) ($result['message'] ?? '');
        if ($message === '') {
            $message = 'Hvis e-posten er registrert, har vi sendt en lenke for å sette nytt passord.';
        }
        if (!($result['ok'] ?? false) && ($result['error'] ?? '') !== '') {
            // Nettverksfeil: vis feil, ellers generisk (API skal normalt alltid være ok)
            if ((int) ($result['status'] ?? 0) >= 500) {
                PortalV3Session::setFlash('error', (string) $result['error']);

                return Response::redirect(PortalPaths::glemtPassord());
            }
        }

        return PortalV3View::render('auth/forgot-password', [
            'submitted' => true,
            'message' => $message,
        ], 'Glemt passord');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function resetForm(): array
    {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        $token = trim((string) ($_GET['token'] ?? ''));

        return PortalV3View::render('auth/reset-password', [
            'token' => $token,
            'error' => $token === '' ? 'Lenken mangler eller er ugyldig.' : '',
        ], 'Sett nytt passord');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function resetSubmit(): array
    {
        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::oversikt());
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        $result = (new AuthPasswordClient())->resetPassword($token, $password, $passwordConfirm);
        if (!($result['ok'] ?? false)) {
            return PortalV3View::render('auth/reset-password', [
                'token' => $token,
                'error' => (string) ($result['error'] ?? 'Kunne ikke tilbakestille passordet.'),
            ], 'Sett nytt passord');
        }

        PortalV3Session::setFlash('success', 'Passordet er oppdatert. Du kan logge inn.');

        return Response::redirect(PortalPaths::login());
    }

    private function absoluteResetUrl(): string
    {
        $base = rtrim((string) (Config::get('app.base_url') ?? $_ENV['APP_BASE_URL'] ?? ''), '/');
        $path = PortalPaths::tilbakestillPassord();
        if ($base !== '') {
            return $base . $path;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return ($https ? 'https' : 'http') . '://' . $host . $path;
    }
}
