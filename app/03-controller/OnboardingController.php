<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\Auth;
use App\Support\ArrangorView;
use App\Support\Response;
use App\Support\Session;
use App\Support\TenantContext;

final class OnboardingController
{
    private const AGREEMENT_VERSION = '1.0';

    public function index(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        if (Auth::hasOrganizerAccess()) {
            return Response::redirect('/');
        }

        return ArrangorView::renderContent('onboarding', 'arrangor/onboarding/terms', [
            'title' => 'Bli arrangør',
            'description' => 'Godta arrangøravtalen for å opprette eller bli med i en arrangørorganisasjon.',
            'agreement_version' => self::AGREEMENT_VERSION,
            'tenant_context' => TenantContext::current(),
        ], false);
    }

    public function acceptTerms(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        if (!isset($_POST['accept_terms'])) {
            Session::setFlash('error', 'Du må godta arrangøravtalen for å fortsette.');

            return Response::redirect('/bli-arrangor');
        }

        $client = new BackendApiClient();
        $result = $client->updateOrganizerProfile([
            'organizer_agreement_version' => self::AGREEMENT_VERSION,
        ]);

        if (!($result['ok'] ?? false)) {
            Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke lagre avtale.'));

            return Response::redirect('/bli-arrangor');
        }

        return Response::redirect('/bli-arrangor/opprett');
    }

    public function createForm(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        if (Auth::hasOrganizerAccess()) {
            return Response::redirect('/');
        }

        $tenantContext = TenantContext::current();

        return ArrangorView::renderContent('onboarding', 'arrangor/onboarding/create', [
            'title' => 'Opprett arrangør',
            'description' => 'Registrer en ny arrangørorganisasjon for inneværende sesong.',
            'tenant_context' => $tenantContext,
            'form' => [
                'name' => '',
                'contact_email' => '',
                'contact_phone' => '',
                'postal_code' => '',
                'city' => '',
            ],
            'error' => $tenantContext['resolved'] ? '' : (string) ($tenantContext['error'] ?? 'Kunne ikke finne cup for dette domenet.'),
        ], false);
    }

    public function createSubmit(): array
    {
        if ($redirect = Auth::requireLogin()) {
            return $redirect;
        }

        $tenantContext = TenantContext::current();
        $tenantId = (int) ($tenantContext['tenant_id'] ?? 0);

        $name = trim((string) ($_POST['name'] ?? ''));
        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
        $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));

        $form = [
            'name' => $name,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'postal_code' => $postalCode,
            'city' => $city,
        ];

        if ($tenantId <= 0) {
            return ArrangorView::renderContent('onboarding', 'arrangor/onboarding/create', [
                'title' => 'Opprett arrangør',
                'description' => 'Registrer en ny arrangørorganisasjon for inneværende sesong.',
                'tenant_context' => $tenantContext,
                'form' => $form,
                'error' => (string) ($tenantContext['error'] ?? 'Kunne ikke finne cup for dette domenet. Sjekk at arrangør-domene er konfigurert i admin.'),
            ], false);
        }

        if ($name === '') {
            return ArrangorView::renderContent('onboarding', 'arrangor/onboarding/create', [
                'title' => 'Opprett arrangør',
                'description' => 'Registrer en ny arrangørorganisasjon for inneværende sesong.',
                'tenant_context' => $tenantContext,
                'form' => $form,
                'error' => 'Arrangørnavn er påkrevd.',
            ], false);
        }

        $client = new BackendApiClient();
        $result = $client->registerOrganizerOrganization([
            'tenant_id' => $tenantId,
            'portal_host' => TenantContext::requestHost(),
            'name' => $name,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'postal_code' => $postalCode,
            'city' => $city,
        ]);

        if (!($result['ok'] ?? false)) {
            return ArrangorView::renderContent('onboarding', 'arrangor/onboarding/create', [
                'title' => 'Opprett arrangør',
                'description' => 'Registrer en ny arrangørorganisasjon for inneværende sesong.',
                'tenant_context' => $tenantContext,
                'form' => $form,
                'error' => (string) ($result['error'] ?? 'Kunne ikke opprette arrangør.'),
            ], false);
        }

        $orgId = (int) ($result['data']['organization']['id'] ?? $result['data']['id'] ?? 0);
        if ($orgId > 0) {
            Session::setSelectedOrganizationId($orgId);
        }

        $me = $client->me();
        if (($me['ok'] ?? false) && is_array($me['data']['user'] ?? null)) {
            Session::setAuth($me['data']['user']);
        }

        $approvalStatus = (string) ($result['data']['approval']['status'] ?? '');
        $message = $approvalStatus === 'pending'
            ? 'Arrangør opprettet. Søknaden venter på godkjenning for sesongen.'
            : 'Arrangør opprettet og godkjent for sesongen.';
        Session::setFlash('success', $message);

        return Response::redirect('/');
    }
}
