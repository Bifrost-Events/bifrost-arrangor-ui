<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\Auth;
use App\Support\Response;
use App\Support\Session;

final class InvitationController
{
    public function acceptForm(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        $token = trim((string) ($_GET['token'] ?? ''));

        return Response::view('arrangor/invitation/accept', [
            'title' => 'Aksepter invitasjon',
            'token' => $token,
            'error' => '',
        ]);
    }

    public function acceptSubmit(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token === '') {
            return Response::view('arrangor/invitation/accept', [
                'title' => 'Aksepter invitasjon',
                'token' => '',
                'error' => 'Ugyldig invitasjonslenke.',
            ], 422);
        }

        $client = new BackendApiClient();
        $result = $client->acceptOrganizerInvitation(['token' => $token]);
        if (!($result['ok'] ?? false)) {
            return Response::view('arrangor/invitation/accept', [
                'title' => 'Aksepter invitasjon',
                'token' => $token,
                'error' => (string) ($result['error'] ?? 'Kunne ikke akseptere invitasjon.'),
            ], (int) ($result['status'] ?? 422));
        }

        $me = $client->me();
        if (($me['ok'] ?? false) && is_array($me['data']['user'] ?? null)) {
            Session::setAuth($me['data']['user']);
        }

        Session::setFlash('success', 'Du er nå medlem av arrangørorganisasjonen.');

        return Response::redirect('/');
    }
}
