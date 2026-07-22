<?php

declare(strict_types=1);

namespace App\Controller\V3;

use App\Service\EventsApiClient;
use App\Service\PortalCupAccess;
use App\Service\PortalV3Services;
use App\Support\PortalPaths;
use App\Support\PortalV3Auth;
use App\Support\PortalV3Session;
use App\Support\PortalV3View;
use App\Support\Response;

final class V3OnboardingController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function getStarted(): array
    {
        $requested = trim((string) ($_GET['step'] ?? ''));
        $ctx = $this->wizardContext();
        $step = $this->resolveWizardStep($ctx, $requested);

        if ($requested !== '' && $requested !== $step && !$this->canVisitStep($requested, $ctx)) {
            return Response::redirect(PortalPaths::komIGang() . '?step=' . rawurlencode($step));
        }

        if ($requested === '' && $step !== PortalV3Session::ONBOARDING_STEP_ACCOUNT) {
            return Response::redirect(PortalPaths::komIGang() . '?step=' . rawurlencode($step));
        }

        return $this->renderWizardStep($step, $ctx, [], []);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function registerSubmit(): array
    {
        $wizardStep = trim((string) ($_POST['wizard_step'] ?? ''));
        if ($wizardStep === PortalV3Session::ONBOARDING_STEP_ORGANIZATION) {
            return $this->wizardOrganizationSubmit();
        }
        if ($wizardStep === PortalV3Session::ONBOARDING_STEP_APPLICATION) {
            return $this->wizardApplicationSubmit();
        }
        if ($wizardStep === PortalV3Session::ONBOARDING_STEP_SERIES) {
            return $this->wizardSeriesSubmit();
        }
        if ($wizardStep === PortalV3Session::ONBOARDING_STEP_DETAILS) {
            return $this->wizardDetailsSubmit();
        }

        if (PortalV3Auth::check()) {
            return Response::redirect(PortalPaths::komIGang());
        }

        $form = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
        ];

        $result = (new PortalV3Services())->auth->register([
            ...$form,
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        ]);

        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Registrering feilet.'));
            $ctx = $this->wizardContext();
            $ctx['form'] = $form;

            return $this->renderWizardStep(PortalV3Session::ONBOARDING_STEP_ACCOUNT, $ctx, $form, []);
        }

        PortalV3Session::setFlash('success', 'Konto opprettet. Fortsett med organisasjon.');

        return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_ORGANIZATION);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function wizardOrganizationSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $action = trim((string) ($_POST['action'] ?? 'create'));
        if ($action === 'select') {
            $orgId = (int) ($_POST['org_id'] ?? 0);
            $ctx = $this->wizardContext();
            $allowed = false;
            foreach ($ctx['organizations'] as $org) {
                if ((int) ($org['org_id'] ?? 0) === $orgId) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                PortalV3Session::setFlash('error', 'Ugyldig organisasjon.');

                return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_ORGANIZATION);
            }
            PortalV3Session::setOnboardingOrgId($orgId);
            PortalV3Session::setOrganizationId($orgId);

            return Response::redirect(PortalPaths::komIGang());
        }

        $body = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'organization_number' => trim((string) ($_POST['organization_number'] ?? '')) ?: null,
            'organization_type' => trim((string) ($_POST['organization_type'] ?? 'organization')) ?: 'organization',
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'website' => trim((string) ($_POST['website'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];

        $result = (new EventsApiClient())->createOrganization($body);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash(
                'error',
                (string) ($result['error'] ?? 'Kunne ikke opprette organisasjon.'),
                is_array($result['errors'] ?? null) ? $result['errors'] : []
            );
            $ctx = $this->wizardContext();

            return $this->renderWizardStep(
                PortalV3Session::ONBOARDING_STEP_ORGANIZATION,
                $ctx,
                $body,
                is_array($result['errors'] ?? null) ? $result['errors'] : [],
            );
        }

        $orgId = (int) ($result['data']['org_id'] ?? 0);
        if ($orgId > 0) {
            PortalV3Session::setOnboardingOrgId($orgId);
            PortalV3Session::setOrganizationId($orgId);
        }
        PortalV3Session::setFlash('success', 'Organisasjonen er opprettet.');

        return Response::redirect(PortalPaths::komIGang());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function wizardApplicationSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        if ($applicationId <= 0) {
            PortalV3Session::setFlash('error', 'Velg en cup.');

            return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_APPLICATION);
        }

        PortalV3Session::setOnboardingApplicationId($applicationId);
        PortalV3Session::setOnboardingSeriesId(null);

        return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_SERIES);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function wizardSeriesSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $ctx = $this->wizardContext();
        $found = null;
        foreach ($ctx['available_series'] as $series) {
            if ((int) ($series['series_id'] ?? 0) === $seriesId && !empty($series['is_accepting'])) {
                $found = $series;
                break;
            }
        }
        if ($found === null) {
            PortalV3Session::setFlash('error', 'Velg en åpen serie.');

            return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_SERIES);
        }

        PortalV3Session::setOnboardingSeriesId($seriesId);
        if ((int) ($found['application_id'] ?? 0) > 0) {
            PortalV3Session::setOnboardingApplicationId((int) $found['application_id']);
        }

        return Response::redirect(PortalPaths::komIGang() . '?step=' . PortalV3Session::ONBOARDING_STEP_DETAILS);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function wizardDetailsSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $ctx = $this->wizardContext();
        $seriesId = (int) ($ctx['onboarding_series_id'] ?? 0);
        $orgId = (int) ($ctx['onboarding_org_id'] ?? 0);
        if ($seriesId <= 0 || $orgId <= 0) {
            return Response::redirect(PortalPaths::komIGang());
        }

        $body = [
            'org_id' => $orgId,
            'message' => trim((string) ($_POST['message'] ?? '')) ?: null,
            'submit' => !empty($_POST['submit_now']),
        ];

        $api = new EventsApiClient();
        $result = $api->createApplication($seriesId, $body);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            PortalV3Session::setFlash(
                'error',
                (string) ($result['error'] ?? 'Kunne ikke lagre søknad.'),
                is_array($result['errors'] ?? null) ? $result['errors'] : []
            );

            return $this->renderWizardStep(
                PortalV3Session::ONBOARDING_STEP_DETAILS,
                $ctx,
                array_merge($body, ['series_id' => $seriesId]),
                is_array($result['errors'] ?? null) ? $result['errors'] : [],
            );
        }

        $id = (int) ($result['data']['organizer_application_id'] ?? 0);
        $shouldSubmit = !empty($_POST['submit_now'])
            && (string) ($result['data']['application_status'] ?? '') === 'draft';
        if ($shouldSubmit && $id > 0) {
            $submit = $api->submitApplication($id);
            if (!($submit['ok'] ?? false)) {
                PortalV3Session::setFlash('error', (string) ($submit['error'] ?? 'Utkast lagret, men innsending feilet.'));

                return Response::redirect($id > 0 ? PortalPaths::arrangorSoknad($id) : PortalPaths::arrangorSoknader());
            }
            PortalV3Session::setFlash('success', 'Søknaden er sendt inn.');
        } else {
            PortalV3Session::setFlash('success', 'Søknaden er lagret.');
        }

        PortalV3Session::setOnboardingSeriesId(null);

        return Response::redirect($id > 0 ? PortalPaths::arrangorSoknad($id) : PortalPaths::arrangorSoknader());
    }

    /**
     * @return array{
     *   logged_in: bool,
     *   domain_bound: bool,
     *   domain_application_id: int|null,
     *   organizations: list<array<string, mixed>>,
     *   onboarding_org_id: int|null,
     *   onboarding_application_id: int|null,
     *   onboarding_series_id: int|null,
     *   available_series: list<array<string, mixed>>,
     *   approved_series: list<array<string, mixed>>,
     *   application_options: list<array{application_id: int, application_name: string}>,
     *   selected_series: array<string, mixed>|null,
     *   form?: array<string, mixed>
     * }
     */
    private function wizardContext(): array
    {
        $services = new PortalV3Services();
        $domain = $services->domainContext->resolveFromRequest();
        $domainAppId = $domain !== null ? (int) ($domain['application_id'] ?? 0) : 0;
        $domainAppKey = $domain !== null ? trim((string) ($domain['application_key'] ?? '')) : '';
        $domainBound = $domainAppId > 0 && $domainAppKey !== '';

        if ($domainBound) {
            $sessionApp = PortalV3Session::getOnboardingApplicationId();
            if ($sessionApp === null || $sessionApp !== $domainAppId) {
                PortalV3Session::setOnboardingApplicationId($domainAppId);
            }
        }

        $organizations = [];
        $availableSeries = [];
        $approvedSeries = [];
        $applicationOptionsSource = [];
        $onboardingOrgId = PortalV3Session::getOnboardingOrgId();
        $onboardingAppId = PortalV3Session::getOnboardingApplicationId();
        $onboardingSeriesId = PortalV3Session::getOnboardingSeriesId();

        if (PortalV3Auth::check()) {
            $api = new EventsApiClient();
            $orgsResult = $api->listMyOrganizations();
            $organizations = ($orgsResult['ok'] ?? false) && is_array($orgsResult['data'] ?? null)
                ? (array) $orgsResult['data']
                : [];

            if ($onboardingOrgId === null && $organizations !== []) {
                $onboardingOrgId = (int) ($organizations[0]['org_id'] ?? 0);
                if ($onboardingOrgId > 0) {
                    PortalV3Session::setOnboardingOrgId($onboardingOrgId);
                }
            } elseif ($onboardingOrgId !== null) {
                $stillValid = false;
                foreach ($organizations as $org) {
                    if ((int) ($org['org_id'] ?? 0) === $onboardingOrgId) {
                        $stillValid = true;
                        break;
                    }
                }
                if (!$stillValid) {
                    $onboardingOrgId = $organizations !== [] ? (int) ($organizations[0]['org_id'] ?? 0) : null;
                    PortalV3Session::setOnboardingOrgId($onboardingOrgId);
                }
            }

            // Domenebundet portal: filtrer på application_key (stabilt på tvers av DB-er).
            // Fri cup-velger: application_id kommer fra Events API og matcher Events-DB.
            $seriesResult = $api->listOnboardingSeries(
                $onboardingOrgId,
                (!$domainBound && $onboardingAppId !== null && $onboardingAppId > 0) ? $onboardingAppId : null,
                null,
                ($domainBound && $domainAppKey !== '') ? $domainAppKey : null,
            );
            $availableSeries = ($seriesResult['ok'] ?? false) && is_array($seriesResult['data'] ?? null)
                ? (array) $seriesResult['data']
                : [];
            $approvedSeries = ($seriesResult['ok'] ?? false) && is_array($seriesResult['meta']['approved_series'] ?? null)
                ? (array) $seriesResult['meta']['approved_series']
                : [];

            $applicationOptionsSource = $availableSeries;
            if (!$domainBound) {
                $allResult = $api->listOnboardingSeries($onboardingOrgId, null);
                $allSeries = ($allResult['ok'] ?? false) && is_array($allResult['data'] ?? null)
                    ? (array) $allResult['data']
                    : [];
                $applicationOptionsSource = $allSeries;
                if ($onboardingAppId === null || $onboardingAppId <= 0) {
                    $availableSeries = $allSeries;
                    $approvedSeries = ($allResult['ok'] ?? false) && is_array($allResult['meta']['approved_series'] ?? null)
                        ? (array) $allResult['meta']['approved_series']
                        : [];
                }
            }
        }

        $applicationOptions = $this->distinctApplications($applicationOptionsSource);
        $selectedSeries = null;
        if ($onboardingSeriesId !== null) {
            foreach ($availableSeries as $series) {
                if ((int) ($series['series_id'] ?? 0) === $onboardingSeriesId) {
                    $selectedSeries = $series;
                    break;
                }
            }
            if ($selectedSeries === null) {
                PortalV3Session::setOnboardingSeriesId(null);
                $onboardingSeriesId = null;
            }
        }

        $approvedIds = [];
        foreach ($approvedSeries as $approved) {
            $approvedIds[(int) ($approved['series_id'] ?? 0)] = true;
        }

        return [
            'logged_in' => PortalV3Auth::check(),
            'domain_bound' => $domainBound,
            'domain_application_id' => $domainBound ? $domainAppId : null,
            'domain_application_name' => $domainBound ? (string) ($domain['application_name'] ?? '') : '',
            'organizations' => $organizations,
            'onboarding_org_id' => $onboardingOrgId,
            'onboarding_application_id' => $onboardingAppId,
            'onboarding_series_id' => $onboardingSeriesId,
            'available_series' => array_values(array_filter(
                $availableSeries,
                static fn (array $s): bool => !empty($s['is_accepting'])
                    && !isset($approvedIds[(int) ($s['series_id'] ?? 0)]),
            )),
            'approved_series' => array_values($approvedSeries),
            'application_options' => $applicationOptions,
            'selected_series' => $selectedSeries,
        ];
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function resolveWizardStep(array $ctx, string $requested): string
    {
        if (!($ctx['logged_in'] ?? false)) {
            return PortalV3Session::ONBOARDING_STEP_ACCOUNT;
        }
        if (($ctx['organizations'] ?? []) === []) {
            return PortalV3Session::ONBOARDING_STEP_ORGANIZATION;
        }
        if (!($ctx['domain_bound'] ?? false)
            && ((int) ($ctx['onboarding_application_id'] ?? 0) <= 0)) {
            return PortalV3Session::ONBOARDING_STEP_APPLICATION;
        }
        if ((int) ($ctx['onboarding_series_id'] ?? 0) <= 0) {
            return PortalV3Session::ONBOARDING_STEP_SERIES;
        }

        if ($requested === PortalV3Session::ONBOARDING_STEP_DONE) {
            return PortalV3Session::ONBOARDING_STEP_DONE;
        }

        return PortalV3Session::ONBOARDING_STEP_DETAILS;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function canVisitStep(string $step, array $ctx): bool
    {
        $order = [
            PortalV3Session::ONBOARDING_STEP_ACCOUNT,
            PortalV3Session::ONBOARDING_STEP_ORGANIZATION,
            PortalV3Session::ONBOARDING_STEP_APPLICATION,
            PortalV3Session::ONBOARDING_STEP_SERIES,
            PortalV3Session::ONBOARDING_STEP_DETAILS,
        ];
        $current = $this->resolveWizardStep($ctx, '');
        $wantIdx = array_search($step, $order, true);
        $curIdx = array_search($current, $order, true);
        if ($wantIdx === false || $curIdx === false) {
            return false;
        }
        // Allow revisiting completed earlier steps
        return $wantIdx <= $curIdx;
    }

    /**
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $form
     * @param array<string, string> $errors
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function renderWizardStep(string $step, array $ctx, array $form, array $errors): array
    {
        $steps = $this->wizardStepMeta($ctx);

        return PortalV3View::render('onboarding/get-started', [
            'wizard_step' => $step,
            'wizard_steps' => $steps,
            'needs_account' => $step === PortalV3Session::ONBOARDING_STEP_ACCOUNT,
            'form' => $form,
            'errors' => $errors,
            'organizations' => $ctx['organizations'],
            'applications' => [],
            'available_series' => $ctx['available_series'],
            'approved_series' => $ctx['approved_series'] ?? [],
            'application_options' => $ctx['application_options'],
            'domain_bound' => $ctx['domain_bound'],
            'domain_application_name' => $ctx['domain_application_name'] ?? '',
            'onboarding_org_id' => $ctx['onboarding_org_id'],
            'onboarding_application_id' => $ctx['onboarding_application_id'],
            'onboarding_series_id' => $ctx['onboarding_series_id'],
            'selected_series' => $ctx['selected_series'],
        ], 'Kom i gang');
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<array{key: string, label: string, status: string}>
     */
    private function wizardStepMeta(array $ctx): array
    {
        $current = $this->resolveWizardStep($ctx, '');
        $defs = [
            ['key' => PortalV3Session::ONBOARDING_STEP_ACCOUNT, 'label' => 'Konto'],
            ['key' => PortalV3Session::ONBOARDING_STEP_ORGANIZATION, 'label' => 'Organisasjon'],
        ];
        if (!($ctx['domain_bound'] ?? false)) {
            $defs[] = ['key' => PortalV3Session::ONBOARDING_STEP_APPLICATION, 'label' => 'Cup'];
        }
        $defs[] = ['key' => PortalV3Session::ONBOARDING_STEP_SERIES, 'label' => 'Sesong'];
        $defs[] = ['key' => PortalV3Session::ONBOARDING_STEP_DETAILS, 'label' => 'Søknad'];

        $keys = array_column($defs, 'key');
        $curIdx = array_search($current, $keys, true);
        if ($curIdx === false) {
            $curIdx = 0;
        }

        $out = [];
        foreach ($defs as $i => $def) {
            $status = 'upcoming';
            if ($i < $curIdx) {
                $status = 'done';
            } elseif ($i === $curIdx) {
                $status = 'current';
            }
            $out[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'status' => $status,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $series
     * @return list<array{application_id: int, application_name: string}>
     */
    private function distinctApplications(array $series): array
    {
        $map = [];
        foreach ($series as $row) {
            $id = (int) ($row['application_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($row['application_name'] ?? ''));
            if ($name === '') {
                $name = (string) ($row['space_name'] ?? ('Cup #' . $id));
            }
            $map[$id] = [
                'application_id' => $id,
                'application_name' => $name,
            ];
        }

        return array_values($map);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function listOrganizations(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $result = (new EventsApiClient())->listMyOrganizations();
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke hente organisasjoner.'));
        }

        return PortalV3View::render('onboarding/organizations', [
            'organizations' => is_array($result['data'] ?? null) ? $result['data'] : [],
        ], 'Mine organisasjoner');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createOrgForm(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        return PortalV3View::render('onboarding/organization-form', [
            'form' => [],
            'errors' => [],
        ], 'Ny organisasjon');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createOrgSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $body = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null,
            'organization_number' => trim((string) ($_POST['organization_number'] ?? '')) ?: null,
            'organization_type' => trim((string) ($_POST['organization_type'] ?? 'organization')) ?: 'organization',
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'website' => trim((string) ($_POST['website'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
        ];

        $result = (new EventsApiClient())->createOrganization($body);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash(
                'error',
                (string) ($result['error'] ?? 'Kunne ikke opprette organisasjon.'),
                is_array($result['errors'] ?? null) ? $result['errors'] : []
            );

            return PortalV3View::render('onboarding/organization-form', [
                'form' => $body,
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
            ], 'Ny organisasjon');
        }

        PortalV3Session::setFlash('success', 'Organisasjonen er opprettet. Du er eier.');

        $orgId = (int) ($result['data']['org_id'] ?? 0);
        if ($orgId > 0) {
            PortalV3Session::setOrganizationId($orgId);
            PortalV3Session::setOnboardingOrgId($orgId);
        }

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === 'wizard' || str_starts_with($returnTo, '/kom-i-gang')) {
            return Response::redirect(PortalPaths::komIGang());
        }

        return Response::redirect(PortalPaths::mineOrganisasjoner());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function listApplications(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new EventsApiClient())->listMyApplications($status !== '' ? $status : null);
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke hente søknader.'));
        }

        $applications = is_array($result['data'] ?? null) ? $result['data'] : [];
        if ($status !== '' && $applications !== []) {
            $applications = array_values(array_filter(
                $applications,
                static fn (array $a): bool => (string) ($a['application_status'] ?? '') === $status
            ));
        }

        return PortalV3View::render('onboarding/applications', [
            'applications' => $applications,
            'filter_status' => $status,
        ], 'Arrangørsøknader');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function showApplication(int $id): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $result = (new EventsApiClient())->getApplication($id);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Søknad ikke funnet.'));

            return Response::redirect(PortalPaths::arrangorSoknader());
        }

        return PortalV3View::render('onboarding/application-show', [
            'application' => $result['data'],
            'owner_mode' => false,
        ], 'Søknad');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function newApplicationForm(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $seriesId = (int) ($_GET['series_id'] ?? 0);
        if ($seriesId > 0) {
            PortalV3Session::setOnboardingSeriesId($seriesId);
        }
        $orgId = (int) ($_GET['org_id'] ?? 0);
        if ($orgId > 0) {
            PortalV3Session::setOnboardingOrgId($orgId);
        }

        return Response::redirect(PortalPaths::komIGang());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function createApplicationSubmit(): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $seriesId = (int) ($_POST['series_id'] ?? 0);
        $body = [
            'org_id' => (int) ($_POST['org_id'] ?? 0),
            'message' => trim((string) ($_POST['message'] ?? '')) ?: null,
        ];
        if (!empty($_POST['requested_series_id'])) {
            $body['requested_series_id'] = (int) $_POST['requested_series_id'];
        }

        if ($seriesId <= 0 || (int) $body['org_id'] <= 0) {
            PortalV3Session::setFlash('error', 'Velg organisasjon og serie.');

            return Response::redirect(PortalPaths::arrangorSoknadNy());
        }

        $result = (new EventsApiClient())->createApplication($seriesId, $body);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            PortalV3Session::setFlash(
                'error',
                (string) ($result['error'] ?? 'Kunne ikke opprette søknad.'),
                is_array($result['errors'] ?? null) ? $result['errors'] : []
            );

            $api = new EventsApiClient();
            $orgsResult = $api->listMyOrganizations();
            $seriesResult = $api->listOnboardingSeries((int) $body['org_id']);

            return PortalV3View::render('onboarding/application-form', [
                'organizations' => is_array($orgsResult['data'] ?? null) ? $orgsResult['data'] : [],
                'available_series' => is_array($seriesResult['data'] ?? null) ? $seriesResult['data'] : [],
                'form' => array_merge($body, ['series_id' => $seriesId]),
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
            ], 'Ny arrangørsøknad');
        }

        $id = (int) ($result['data']['organizer_application_id'] ?? 0);
        PortalV3Session::setFlash('success', 'Utkast til søknad er lagret.');

        return Response::redirect($id > 0 ? PortalPaths::arrangorSoknad($id) : PortalPaths::arrangorSoknader());
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function submitApplication(int $id): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $result = (new EventsApiClient())->submitApplication($id);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Søknaden er sendt inn.');
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke sende inn søknad.'));
        }

        return Response::redirect(PortalPaths::arrangorSoknad($id));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function withdrawApplication(int $id): array
    {
        if ($redirect = PortalV3Auth::requireLogin()) {
            return $redirect;
        }

        $result = (new EventsApiClient())->withdrawApplication($id);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Søknaden er trukket.');
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke trekke søknad.'));
        }

        return Response::redirect(PortalPaths::arrangorSoknad($id));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function listSeriesApplications(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        if ($orgId <= 0) {
            PortalV3Session::setFlash('error', 'Velg en organisasjon først.');

            return Response::redirect(PortalPaths::kontekstOrganisasjon());
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $result = (new EventsApiClient())->listSeriesApplications(
            $orgId,
            $seriesId,
            $status !== '' ? $status : null
        );
        if (!($result['ok'] ?? false)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke hente søknader.'));
        }

        $personId = PortalV3Auth::personId() ?? 0;
        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        $access = [];
        if (is_array($series)) {
            $spaceId = (int) ($series['space_id'] ?? 0);
            $space = $spaceId > 0
                ? $services->eventSpaces->findAccessibleForPerson($personId, $spaceId)
                : null;
            if (is_array($space)) {
                $access = (new PortalCupAccess($services))->forSpace($personId, $space);
            }
        }
        if (!($access['can_manage_cup'] ?? false)) {
            PortalV3Session::setFlash('error', 'Kun serieeier kan behandle søknader.');

            return Response::redirect(PortalPaths::oversikt());
        }

        return PortalV3View::render('onboarding/series-applications', [
            'series_id' => $seriesId,
            'series' => $series,
            'applications' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'filter_status' => $status,
            'org_id' => $orgId,
            'onboarding_mode' => $this->resolveOnboardingMode($series),
        ], 'Søknader om stevne');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function updateSeriesOnboardingSettings(int $seriesId): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        if ($deny = $this->requireSeriesOwner($services, $seriesId)) {
            return $deny;
        }

        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        $mode = trim((string) ($_POST['mode'] ?? 'closed'));
        $allowed = ['closed', 'open', 'approval_required', 'invite_only'];
        if (!in_array($mode, $allowed, true)) {
            $mode = 'closed';
        }

        $result = (new EventsApiClient())->updateOnboardingSettings($orgId, $seriesId, [
            'mode' => $mode,
            'allow_new_organizations' => true,
            'require_organization_number' => false,
        ]);

        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Innstillinger for arrangørsøknader er lagret.');
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke lagre innstillinger.'));
        }

        return Response::redirect(PortalPaths::serieSoknader($seriesId));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function showSeriesApplication(int $seriesId, int $id): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }

        if ($deny = $this->requireSeriesOwner($services, $seriesId)) {
            return $deny;
        }

        $result = (new EventsApiClient())->getApplication($id);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Søknad ikke funnet.'));

            return Response::redirect(PortalPaths::serieSoknader($seriesId));
        }

        return PortalV3View::render('onboarding/series-application-show', [
            'series_id' => $seriesId,
            'application' => $result['data'],
            'owner_mode' => true,
        ], 'Behandle søknad');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function approve(int $seriesId, int $id): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }
        if ($deny = $this->requireSeriesOwner($services, $seriesId)) {
            return $deny;
        }

        $body = [
            'review_notes' => trim((string) ($_POST['review_notes'] ?? '')) ?: null,
            'create_event_draft' => false,
        ];
        $result = (new EventsApiClient())->approveApplication($id, $body);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash(
                'success',
                'Organisasjonen er godkjent som arrangør for sesongen og kan opprette stevner fritt.',
            );
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke godkjenne.'));
        }

        return Response::redirect(PortalPaths::serieSoknad($seriesId, $id));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function reject(int $seriesId, int $id): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }
        if ($deny = $this->requireSeriesOwner($services, $seriesId)) {
            return $deny;
        }

        $notes = trim((string) ($_POST['review_notes'] ?? ''));
        if ($notes === '') {
            PortalV3Session::setFlash('error', 'Begrunnelse (review_notes) er påkrevd ved avvisning.');

            return Response::redirect(PortalPaths::serieSoknad($seriesId, $id));
        }

        $result = (new EventsApiClient())->rejectApplication($id, ['review_notes' => $notes]);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Søknaden er avvist.');
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke avvise.'));
        }

        return Response::redirect(PortalPaths::serieSoknad($seriesId, $id));
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function setUnderReview(int $seriesId, int $id): array
    {
        $services = new PortalV3Services();
        if ($redirect = PortalV3Auth::requirePortalAccess($services->organizationContext)) {
            return $redirect;
        }
        if ($deny = $this->requireSeriesOwner($services, $seriesId)) {
            return $deny;
        }

        $body = ['application_status' => 'under_review'];
        $notes = trim((string) ($_POST['review_notes'] ?? ''));
        if ($notes !== '') {
            $body['review_notes'] = $notes;
        }

        $result = (new EventsApiClient())->patchApplication($id, $body);
        if ($result['ok'] ?? false) {
            PortalV3Session::setFlash('success', 'Søknaden er satt under behandling.');
        } else {
            PortalV3Session::setFlash('error', (string) ($result['error'] ?? 'Kunne ikke oppdatere status.'));
        }

        return Response::redirect(PortalPaths::serieSoknad($seriesId, $id));
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    private function requireSeriesOwner(PortalV3Services $services, int $seriesId): ?array
    {
        $personId = PortalV3Auth::personId() ?? 0;
        $orgId = (int) ($services->organizationContext->activeOrganizationId() ?? 0);
        if ($orgId <= 0) {
            PortalV3Session::setFlash('error', 'Velg en organisasjon først.');

            return Response::redirect(PortalPaths::kontekstOrganisasjon());
        }

        $series = $services->series->findAccessible($personId, $seriesId, $orgId);
        if ($series === null) {
            PortalV3Session::setFlash('error', 'Serie ikke funnet.');

            return Response::redirect(PortalPaths::oversikt());
        }

        $spaceId = (int) ($series['space_id'] ?? 0);
        $space = $spaceId > 0
            ? $services->eventSpaces->findAccessibleForPerson($personId, $spaceId)
            : null;
        $access = is_array($space)
            ? (new PortalCupAccess($services))->forSpace($personId, $space)
            : [];
        if (!($access['can_manage_cup'] ?? false)) {
            PortalV3Session::setFlash('error', 'Kun serieeier kan behandle søknader.');

            return Response::redirect(PortalPaths::oversikt());
        }

        return null;
    }

    /** @param array<string, mixed>|null $series */
    private function resolveOnboardingMode(?array $series): string
    {
        if ($series === null) {
            return 'closed';
        }
        $raw = $series['settings_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return 'closed';
        }
        $mode = (string) (($raw['organizer_onboarding']['mode'] ?? '') ?: 'closed');

        return $mode !== '' ? $mode : 'closed';
    }
}
