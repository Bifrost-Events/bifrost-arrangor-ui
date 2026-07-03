<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\ArrangorView;
use App\Support\Response;
use App\Support\Session;

final class OrganizationController
{
    public function profile(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);

        return ArrangorView::renderContent('organization.profile', 'arrangor/organization/profile', [
            'context' => $context,
        ]);
    }

    public function members(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);

        $members = $orgId > 0
            ? $client->organizerMembers($orgId)
            : ['ok' => false, 'data' => null, 'error' => 'Ingen arrangør valgt'];

        return ArrangorView::renderContent('organization.members', 'arrangor/organization/members', [
            'members' => $members,
            'context' => $context,
            'invite_email' => '',
            'error' => '',
        ]);
    }

    public function inviteSubmit(): array
    {
        $client = new BackendApiClient();
        $context = ArrangorView::resolveOrganizerContext($client);
        $orgId = (int) ($context['selected_organization_id'] ?? 0);
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($orgId <= 0) {
            Session::setFlash('error', 'Velg en arrangør først.');

            return Response::redirect('/organisasjon/medlemmer');
        }

        if ($email === '') {
            return ArrangorView::renderContent('organization.members', 'arrangor/organization/members', [
                'members' => $client->organizerMembers($orgId),
                'context' => $context,
                'invite_email' => $email,
                'error' => 'E-post er påkrevd for invitasjon.',
            ]);
        }

        $result = $client->inviteOrganizerMember($orgId, ['email' => $email]);
        if (!($result['ok'] ?? false)) {
            return ArrangorView::renderContent('organization.members', 'arrangor/organization/members', [
                'members' => $client->organizerMembers($orgId),
                'context' => $context,
                'invite_email' => $email,
                'error' => (string) ($result['error'] ?? 'Kunne ikke sende invitasjon.'),
            ]);
        }

        Session::setFlash('success', 'Invitasjon sendt.');

        return Response::redirect('/organisasjon/medlemmer');
    }
}
