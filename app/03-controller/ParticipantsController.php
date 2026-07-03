<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\Response;
use App\Support\Session;

final class ParticipantsController
{
    public function index(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        $participants = $orgId > 0
            ? $client->organizerParticipants($orgId)
            : ['ok' => false, 'data' => null, 'error' => 'Ingen arrangør valgt'];

        return ArrangorView::renderContent('participants.list', 'arrangor/participants/list', [
            'participants' => $participants,
            'context' => $context,
            'error' => '',
        ]);
    }

    public function createSubmit(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        if ($orgId <= 0) {
            Session::setFlash('error', 'Velg en arrangør først.');

            return Response::redirect('/deltakere');
        }

        $body = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
        ];

        $result = $client->createOrganizerParticipant($orgId, $body);
        if (!($result['ok'] ?? false)) {
            return ArrangorView::renderContent('participants.list', 'arrangor/participants/list', [
                'participants' => $client->organizerParticipants($orgId),
                'context' => $context,
                'error' => (string) ($result['error'] ?? 'Kunne ikke opprette deltaker.'),
            ]);
        }

        Session::setFlash('success', 'Deltaker opprettet.');

        return Response::redirect('/deltakere');
    }
}
