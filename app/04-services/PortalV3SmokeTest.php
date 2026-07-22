<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalEventRepository;
use App\Repository\Api\ApiPortalSeriesRepository;
use App\Repository\Api\ApiPortalSpaceRepository;
use App\Repository\Pdo\PdoPortalUserRepository;
use App\Support\Config;
use App\Support\Database;
use App\Support\PortalCupBrand;
use App\Support\PortalV3;
use App\Support\PortalV3Menu;
use App\Support\PortalV3Session;
use PDO;
use ReflectionClass;

final class PortalV3SmokeTest
{
    public const DEMO_EMAIL = 'skytecuper-admin@demo.bifrost.local';
    public const DEMO_PASSWORD = 'Demo123!';

    public function __construct(
        private readonly ?PDO $pdo = null,
    ) {
    }

    /** @return list<string> */
    public function run(): array
    {
        if (!PortalV3::isEnabled()) {
            throw new \RuntimeException('V3-portalen er deaktivert (ORGANIZER_PORTAL_V3_ENABLED=false)');
        }

        $pdo = $this->pdo ?? Database::pdo();
        $lines = [];

        $this->assertNoPdoEventRepositories();
        $lines[] = 'OK: ingen PdoPortal Event-repositories i V3-portalen';

        $eventsUrl = (string) Config::get('events.api_base_url', '');
        if ($eventsUrl === '') {
            throw new \RuntimeException(
                'Events API base URL kunne ikke utledes — sett BACKEND_URL (api.* → admin.*) eller EVENTS_URL'
            );
        }
        $lines[] = 'OK: Events API base URL (' . $eventsUrl . ')';

        $services = new PortalV3Services($pdo);
        $this->assertApiWiring($services);
        $lines[] = 'OK: PortalV3Services bruker ApiPortal*-repositories for Events';

        $personId = $this->findDemoPersonId($pdo);
        $lines[] = 'OK: demo-person funnet (person_id=' . $personId . ')';

        $orgs = $services->organizationContext->administrableOrganizations($personId);
        if ($orgs === []) {
            throw new \RuntimeException('Demo-bruker har ingen administrerbare organisasjoner — kjør events seed 004_demo_portal_org_admin.sql');
        }
        $skytecuperOrg = null;
        foreach ($orgs as $org) {
            if (($org['short_name'] ?? '') === 'Skytecuper' || str_contains((string) ($org['name'] ?? ''), 'Skytecuper')) {
                $skytecuperOrg = $org;
                break;
            }
        }
        if ($skytecuperOrg === null) {
            $skytecuperOrg = $orgs[0];
        }
        $orgId = (int) ($skytecuperOrg['org_id'] ?? 0);
        $lines[] = 'OK: org_admin-medlemskap (org_id=' . $orgId . ', name=' . ($skytecuperOrg['name'] ?? '') . ')';

        if (!$services->organizationPolicy->canAdministerOrganization($personId, $orgId)) {
            throw new \RuntimeException('OrganizationPolicy::canAdministerOrganization returnerte false');
        }
        $lines[] = 'OK: OrganizationPolicy::canAdministerOrganization';

        PortalV3Session::setAuth([
            'user_id' => $this->verifyDemoCredentials($pdo, $personId),
            'person_id' => $personId,
            'email' => self::DEMO_EMAIL,
            'name' => 'Demo Admin',
        ]);

        $spacesAcross = $services->eventSpaces->listAdministrable($personId, null, false);
        if ($spacesAcross === []) {
            $apiErr = EventsApiClient::lastListError();
            throw new \RuntimeException(
                'listAdministrable uten domain-filter returnerte tom liste for demo-bruker'
                . ($apiErr ? (' (' . $apiErr . ')') : '')
            );
        }
        $lines[] = 'OK: listAdministrable (alle cuper, count=' . count($spacesAcross) . ')';

        $skyteAppId = (int) ($spacesAcross[0]['application_id'] ?? 0);
        foreach ($spacesAcross as $spaceRow) {
            if ((int) ($spaceRow['owner_org_id'] ?? 0) === $orgId) {
                $skyteAppId = (int) ($spaceRow['application_id'] ?? 0);
                break;
            }
        }
        $filtered = $services->eventSpaces->listAdministrable($personId, $skyteAppId > 0 ? $skyteAppId : null, false);
        $lines[] = 'OK: listAdministrable filtrert på application_id=' . $skyteAppId
            . ' (count=' . count($filtered) . ')';

        $login = (int) (PortalV3Session::getAuth()['user_id'] ?? 0);
        $lines[] = 'OK: auth-bruker med gyldig passord (' . self::DEMO_EMAIL . ', user_id=' . $login . ')';

        $demo = $this->loadDemoEventGraph($pdo, $orgId);
        $lines[] = 'OK: demo Event Space (space_id=' . $demo['space_id'] . ')';
        $lines[] = 'OK: demo seriehierarki (root=' . $demo['root_id'] . ', round=' . $demo['round_id'] . ')';
        $lines[] = 'OK: demo arrangement (event_id=' . $demo['event_id'] . ')';

        $space = $demo['space'];
        $round = $demo['round'];
        $event = $demo['event'];

        if (!$services->eventPolicy->canEdit($personId, $event, $orgId)) {
            throw new \RuntimeException('EventPolicy::canEdit skulle være true for eget arrangement');
        }
        $lines[] = 'OK: EventPolicy::canEdit for eget arrangement';

        if (!$services->spacePolicy->canManageSeries($personId, $space, $orgId)) {
            throw new \RuntimeException('EventSpacePolicy::canManageSeries skulle være true');
        }
        $lines[] = 'OK: EventSpacePolicy::canManageSeries for serieeier';

        if (!$services->seriesPolicy->canCreateChildSeries($personId, $round, $orgId)) {
            throw new \RuntimeException('SeriesPolicy::canCreateChildSeries skulle være true');
        }
        $lines[] = 'OK: SeriesPolicy::canCreateChildSeries';

        $labels = $services->labels->resolveForSpace($space);
        if ($labels->singular('event') !== 'Stevne' || $labels->plural('series') !== 'Sesonger') {
            throw new \RuntimeException(
                'Terminologi fra Event Space feil: event=' . $labels->singular('event')
                . ', series_plural=' . $labels->plural('series')
            );
        }
        $lines[] = 'OK: EventLabelResolver (Stevne / Sesonger)';

        $otherOrgId = $this->findOtherOrgId($pdo, $orgId);
        if ($otherOrgId > 0) {
            $otherEvent = $this->findEventOwnedByOrg($pdo, $otherOrgId);
            if ($otherEvent !== null) {
                if ($services->eventPolicy->canEdit($personId, $otherEvent, $orgId)) {
                    throw new \RuntimeException('EventPolicy::canEdit skulle være false for annen orgs arrangement');
                }
                $lines[] = 'OK: avslag på arrangement eid av annen org (org_id=' . $otherOrgId . ')';

                if ($services->seriesPolicy->canEdit($personId, $round, $otherOrgId)) {
                    throw new \RuntimeException('SeriesPolicy::canEdit skulle være false for annen orgs serie');
                }
                $lines[] = 'OK: avslag på serie eid av annen org';

                $foreignInSeries = ['owner_org_id' => $otherOrgId, 'series_id' => (int) ($round['series_id'] ?? 0)];
                if (!$services->eventPolicy->canView($personId, $foreignInSeries, $orgId, $round)) {
                    throw new \RuntimeException('Serieeier skal kunne se arrangement i egen serie (policy)');
                }
                if ($services->eventPolicy->canEdit($personId, $foreignInSeries, $orgId)) {
                    throw new \RuntimeException('Serieeier skal ikke kunne redigere annen orgs arrangement');
                }
                $lines[] = 'OK: serieeier kan se (ikke redigere) fremmed arrangement i egen serie (policy)';
            }
        } else {
            $lines[] = 'INFO: ingen annen demo-org funnet — negative tester hoppet over';
        }

        $pp = \App\Support\PortalPaths::class;
        $rootId = (int) ($demo['root_id'] ?? 0);
        if ($rootId < 1) {
            throw new \RuntimeException('Mangler demo root series for struktur/scoring-UI');
        }
        if ($pp::sesongStruktur($rootId) !== '/sesonger/' . $rootId . '/struktur'
            || $pp::sesongSammenlagt($rootId) !== '/sesonger/' . $rootId . '/sammenlagt') {
            throw new \RuntimeException('PortalPaths for struktur/sammenlagt er feil');
        }
        $lines[] = 'OK: PortalPaths sesong struktur/sammenlagt';

        $rootSeries = $services->series->findAccessible($personId, $rootId, $orgId);
        if ($rootSeries === null || !$services->seriesPolicy->canEdit($personId, $rootSeries, $orgId)) {
            throw new \RuntimeException('Cupadmin skal kunne åpne sesong for struktur/scoring');
        }
        $bundle = $services->series->getSeasonStructureScoring($personId, $orgId, $rootId);
        if ($bundle === null) {
            throw new \RuntimeException('getSeasonStructureScoring skulle returnere data for cupadmin');
        }
        $htmlStruct = $this->renderSeasonStructureSnippet($space, $rootSeries, $bundle, $labels);
        if (!str_contains($htmlStruct, 'Sesongstruktur') || !str_contains($htmlStruct, 'Stevner direkte i sesongen')) {
            throw new \RuntimeException('Struktur-UI mangler forventet innhold');
        }
        if (!str_contains($htmlStruct, 'gruppert i runder')) {
            throw new \RuntimeException('Struktur-UI mangler runde-valg');
        }
        $lines[] = 'OK: cupadmin kan åpne struktur-UI';

        $seriesForScore = is_array($bundle['series'] ?? null) && $bundle['series'] !== []
            ? $bundle['series']
            : $rootSeries;
        $htmlScore = $this->renderSeasonScoringSnippet($space, $seriesForScore, $bundle, $labels);
        if (!str_contains($htmlScore, 'Sammenlagtregler') || !str_contains($htmlScore, 'Faktisk stevneresultat')) {
            throw new \RuntimeException('Sammenlagt-UI mangler forventet innhold');
        }
        if (!str_contains($htmlScore, 'Cup-poeng etter plassering')) {
            throw new \RuntimeException('Sammenlagt-UI mangler plasseringspoeng-valg');
        }
        $lines[] = 'OK: sammenlagtregler-UI rendres';

        if ($otherOrgId > 0) {
            $denied = $services->series->updateSeasonStructure($personId, $otherOrgId, $rootId, ['structure_type' => 'rounds']);
            if ($denied['ok'] ?? false) {
                throw new \RuntimeException('Arrangøradmin/feil org skal ikke kunne lagre sesongstruktur');
            }
            $lines[] = 'OK: lagring av sesongregler avvises uten cupadmin-kontekst';
        }

        $lines = array_merge($lines, $this->runInformationArchitectureChecks($services, $personId, $orgId, $space));
        $lines = array_merge($lines, $this->runOrganizerAccountOnboardingChecks($services, $pdo));

        $lines[] = 'INFO: kjør «php modules/events/bin/console organizer-api-test» i bifrost-admin-core for live API-verifisering';
        $lines[] = 'Alle Portal V3 smoke-tester bestått (API-only Events).';

        return $lines;
    }

    /**
     * Ny arrangør uten eksisterende bruker: kontoopprettelse som første steg.
     *
     * @return list<string>
     */
    private function runOrganizerAccountOnboardingChecks(PortalV3Services $services, PDO $pdo): array
    {
        $lines = [];
        $pp = \App\Support\PortalPaths::class;

        if ($pp::komIGang() !== '/kom-i-gang') {
            throw new \RuntimeException('PortalPaths::komIGang skal være /kom-i-gang');
        }
        $lines[] = 'OK: PortalPaths::komIGang';

        $loginHtml = $this->renderLoginSnippet();
        if (!str_contains($loginHtml, 'Kom i gang') || !str_contains($loginHtml, $pp::komIGang())) {
            throw new \RuntimeException('Innloggingsside skal lenke til Kom i gang for nye arrangører');
        }
        if (!str_contains($loginHtml, 'Glemt passord') || !str_contains($loginHtml, $pp::glemtPassord())) {
            throw new \RuntimeException('Innloggingsside skal lenke til Glemt passord');
        }
        if (!str_contains($loginHtml, 'arrangør') && !str_contains($loginHtml, 'Arrangør')) {
            throw new \RuntimeException('Innloggingsside skal ha norsk hjelpetekst om arrangør');
        }
        if (str_contains($loginHtml, 'Bifrost core og Events API')) {
            throw new \RuntimeException('Innloggingsside skal ikke vise teknisk API-jargon');
        }
        $lines[] = 'OK: login-side lenker til Kom i gang og Glemt passord';

        if ($pp::glemtPassord() !== '/glemt-passord' || $pp::tilbakestillPassord() !== '/tilbakestill-passord') {
            throw new \RuntimeException('PortalPaths for glemt/tilbakestill passord er feil');
        }
        $lines[] = 'OK: PortalPaths glemt/tilbakestill passord';

        $guestHtml = $this->renderGetStartedSnippet('account', []);
        if (!str_contains($guestHtml, 'Opprett konto') || !str_contains($guestHtml, 'name="password"')) {
            throw new \RuntimeException('Gjest på Kom i gang skal se kontoopprettelse');
        }
        if (str_contains($guestHtml, 'Åpne serier')) {
            throw new \RuntimeException('Veiviser skal ikke vise global Åpne serier-liste');
        }
        $lines[] = 'OK: gjest ser kontoopprettelse på Kom i gang';

        $orgStepHtml = $this->renderGetStartedSnippet('organization', []);
        if (!str_contains($orgStepHtml, 'Organisasjon') || str_contains($orgStepHtml, 'Opprett konto og fortsett')) {
            throw new \RuntimeException('Organisasjonssteg i veiviser mangler eller viser konto-skjema');
        }
        if (!str_contains($orgStepHtml, 'Fremdrift') && !str_contains($orgStepHtml, 'Konto')) {
            throw new \RuntimeException('Veiviser skal ha stegindikator');
        }
        $lines[] = 'OK: veiviser organisasjonssteg + stegindikator';

        $seriesStepHtml = $this->renderGetStartedSnippet('series', [], [
            'available_series' => [
                [
                    'series_id' => 1,
                    'name' => 'Namdal 2027',
                    'space_name' => 'Jaktfeltkarusell Namdal',
                    'application_id' => 10,
                    'is_accepting' => true,
                ],
            ],
            'approved_series' => [
                [
                    'series_id' => 2,
                    'name' => 'Namdal 2026',
                    'season_label' => '2026',
                ],
            ],
            'domain_bound' => true,
            'domain_application_name' => 'Jaktfeltkarusell Namdal',
        ]);
        if (!str_contains($seriesStepHtml, 'Namdal 2027') || str_contains($seriesStepHtml, 'Jaktfeltcup 2027')) {
            throw new \RuntimeException('Seriesteg skal kun vise filtrerte serier for cupen');
        }
        if (!str_contains($seriesStepHtml, 'Jaktfeltkarusell Namdal')) {
            throw new \RuntimeException('Seriesteg skal nevne domenbundet cup');
        }
        if (!str_contains($seriesStepHtml, 'type="radio"') || str_contains($seriesStepHtml, '<select')) {
            throw new \RuntimeException('Seriesteg skal bruke radio-liste, ikke dropdown');
        }
        if (!str_contains($seriesStepHtml, 'Namdal 2026') || !str_contains($seriesStepHtml, 'Allerede godkjent')) {
            throw new \RuntimeException('Seriesteg skal liste allerede godkjente sesonger');
        }
        $lines[] = 'OK: seriesteg er cup-avgrenset (radio + godkjente sesonger)';

        $auth = $services->auth;
        $unique = 'smoke.arrangor.' . time() . '.' . bin2hex(random_bytes(3)) . '@bifrost.test';

        $short = $auth->register([
            'first_name' => 'Smoke',
            'last_name' => 'Arrangor',
            'email' => 'smoke.short.' . time() . '@bifrost.test',
            'password' => 'short',
            'password_confirm' => 'short',
        ]);
        if (($short['ok'] ?? true) !== false) {
            throw new \RuntimeException('Kort passord i arrangør-register skal feile');
        }
        $lines[] = 'OK: register avviser passord < 8';

        PortalV3Session::clearAuth();
        $reg = $auth->register([
            'first_name' => 'Smoke',
            'last_name' => 'Arrangor',
            'email' => $unique,
            'phone' => '90000002',
            'password' => 'SmokeArr123!',
            'password_confirm' => 'SmokeArr123!',
        ]);
        if (!($reg['ok'] ?? false)) {
            throw new \RuntimeException('register for ny arrangør feilet: ' . ($reg['error'] ?? ''));
        }
        $user = $reg['user'] ?? [];
        $userId = (int) ($user['user_id'] ?? 0);
        $newPersonId = (int) ($user['person_id'] ?? 0);
        if ($userId <= 0 || $newPersonId <= 0) {
            throw new \RuntimeException('register mangler user_id/person_id');
        }
        if ((int) (PortalV3Session::getAuth()['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('register skal logge inn ny arrangør');
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM org_memberships WHERE person_id = ? AND deleted_at IS NULL');
        $stmt->execute([$newPersonId]);
        if ((int) $stmt->fetchColumn() !== 0) {
            throw new \RuntimeException('Kontoopprettelse skal ikke opprette org-medlemskap');
        }
        $lines[] = 'OK: ny arrangør kan opprette konto uten eksisterende bruker (user_id=' . $userId . ')';

        $dup = $auth->register([
            'first_name' => 'Dup',
            'last_name' => 'Arr',
            'email' => $unique,
            'password' => 'SmokeArr123!',
            'password_confirm' => 'SmokeArr123!',
        ]);
        if (($dup['ok'] ?? true) !== false) {
            throw new \RuntimeException('Duplikat e-post i arrangør-register skal feile');
        }
        $lines[] = 'OK: duplikat e-post avvist ved arrangør-register';

        $menu = PortalV3Menu::build(
            $services->labels->resolveForSpace(null),
            null,
            '/kom-i-gang',
            false,
            [],
        );
        $menuLabels = array_column($menu, 'label');
        if (in_array('Kom i gang', $menuLabels, true)) {
            throw new \RuntimeException('Kom i gang skal ikke ligge i hovedmeny');
        }
        $accountLabels = array_column(PortalV3Menu::accountLinks(), 'label');
        if (!in_array('Kom i gang', $accountLabels, true)
            || !in_array('Mine organisasjoner', $accountLabels, true)
            || !in_array('Arrangørsøknader', $accountLabels, true)) {
            throw new \RuntimeException('Brukermeny skal inneholde Kom i gang, Mine organisasjoner og Arrangørsøknader');
        }
        $lines[] = 'OK: onboarding-lenker ligger i brukermeny, ikke hovedmeny';

        // Soft-cleanup
        PortalV3Session::clearAuth();
        $pdo->prepare("UPDATE auth_users SET status='inactive', deleted_at=NOW() WHERE user_id = ?")
            ->execute([$userId]);
        $pdo->prepare("UPDATE person_people SET status='inactive', deleted_at=NOW() WHERE person_id = ?")
            ->execute([$newPersonId]);
        $lines[] = 'OK: opprydding av smoke-arrangørkonto';

        return $lines;
    }

    private function renderLoginSnippet(): string
    {
        $pp = \App\Support\PortalPaths::class;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/login.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $extra
     */
    private function renderGetStartedSnippet(string $step, array $form, array $extra = []): string
    {
        $wizard_step = $step;
        $wizard_steps = [
            ['key' => 'account', 'label' => 'Konto', 'status' => $step === 'account' ? 'current' : 'done'],
            ['key' => 'organization', 'label' => 'Organisasjon', 'status' => $step === 'organization' ? 'current' : ($step === 'account' ? 'upcoming' : 'done')],
            ['key' => 'series', 'label' => 'Sesong', 'status' => $step === 'series' ? 'current' : 'upcoming'],
            ['key' => 'details', 'label' => 'Søknad', 'status' => 'upcoming'],
        ];
        $needs_account = $step === 'account';
        $errors = [];
        $organizations = $extra['organizations'] ?? [];
        $applications = [];
        $available_series = $extra['available_series'] ?? [];
        $approved_series = $extra['approved_series'] ?? [];
        $application_options = $extra['application_options'] ?? [];
        $domain_bound = (bool) ($extra['domain_bound'] ?? false);
        $domain_application_name = (string) ($extra['domain_application_name'] ?? '');
        $onboarding_org_id = $extra['onboarding_org_id'] ?? null;
        $onboarding_application_id = $extra['onboarding_application_id'] ?? null;
        $onboarding_series_id = $extra['onboarding_series_id'] ?? null;
        $selected_series = $extra['selected_series'] ?? null;
        $pp = \App\Support\PortalPaths::class;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/onboarding/get-started.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $space
     * @param array<string, mixed> $series
     * @param array<string, mixed> $bundle
     */
    private function renderSeasonStructureSnippet(array $space, array $series, array $bundle, PortalEventTerminology $labels): string
    {
        $structure_bundle = $bundle;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/series/structure.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $space
     * @param array<string, mixed> $series
     * @param array<string, mixed> $bundle
     */
    private function renderSeasonScoringSnippet(array $space, array $series, array $bundle, PortalEventTerminology $labels): string
    {
        $structure_bundle = $bundle;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/series/scoring.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $space
     * @param list<array<string, mixed>> $seasonBlocks
     * @param array<string, mixed> $extra
     */
    private function renderArrangerSpaceIndexSnippet(
        array $space,
        PortalEventTerminology $labels,
        array $seasonBlocks,
        array $extra = [],
    ): string {
        $organizers = [];
        $filter_organizer_id = 0;
        $filter_status = '';
        $filter_when = '';
        $filter_season_scope = 'all';
        $season_label = '';
        $is_arranger_view = true;
        $arranger_name = (string) ($extra['arranger_name'] ?? '');
        $season_blocks = $seasonBlocks;
        $events = [];
        foreach ($seasonBlocks as $block) {
            foreach ($block['events'] ?? [] as $event) {
                $events[] = $event;
            }
            foreach ($block['rounds'] ?? [] as $round) {
                foreach ($round['events'] ?? [] as $event) {
                    $events[] = $event;
                }
            }
        }
        $pp = \App\Support\PortalPaths::class;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/events/space-index.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $space
     * @param array<string, mixed> $season
     * @param list<array{series_id: int, round_label: string, name: string, location_name: string, starts_at: string}> $rows
     */
    private function renderBatchFormSnippet(
        array $space,
        array $season,
        PortalEventTerminology $labels,
        array $rows,
    ): string {
        $pp = \App\Support\PortalPaths::class;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/events/batch-form.php';

        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $application */
    private function renderApplicationShowSnippet(array $application): string
    {
        $pp = \App\Support\PortalPaths::class;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/onboarding/application-show.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $space
     * @return list<string>
     */
    private function runInformationArchitectureChecks(
        PortalV3Services $services,
        int $personId,
        int $orgId,
        array $space,
    ): array {
        $lines = [];
        $spaceId = (int) ($space['space_id'] ?? 0);
        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        if (!($access['can_manage_cup'] ?? false)) {
            throw new \RuntimeException('Demo-bruker skal være cupadministrator for smoke-space');
        }
        $lines[] = 'OK: cupadministrator-deteksjon (space.owner_org)';

        $menuCup = PortalV3Menu::build(
            $services->labels->resolveForSpace($space),
            $space,
            '/cup',
            true,
            $access,
        );
        $menuLabels = array_column($menuCup, 'label');
        if (!in_array('Cupadministrasjon', $menuLabels, true) || !in_array('Arrangører', $menuLabels, true)) {
            throw new \RuntimeException('Cupadmin-meny mangler Cupadministrasjon/Arrangører');
        }
        if (in_array('Kom i gang', $menuLabels, true) || in_array('Mine organisasjoner', $menuLabels, true)) {
            throw new \RuntimeException('Cupadmin-hovedmeny skal ikke inneholde onboarding-lenker');
        }
        $lines[] = 'OK: cupadmin-hovedmeny inneholder Cupadministrasjon og Arrangører';

        $arrangerAccess = $access;
        $arrangerAccess['can_manage_cup'] = false;
        $arrangerAccess['can_view_arrangers'] = false;
        $arrangerAccess['can_view_all_events'] = false;
        $menuArranger = PortalV3Menu::build(
            $services->labels->resolveForSpace($space),
            $space,
            '/stevner',
            true,
            $arrangerAccess,
        );
        $arrangerLabels = array_column($menuArranger, 'label');
        if (in_array('Cupadministrasjon', $arrangerLabels, true) || in_array('Arrangører', $arrangerLabels, true)) {
            throw new \RuntimeException('Ikke-cupadmin skal ikke se Cupadministrasjon/Arrangører i meny');
        }
        if (count($arrangerLabels) < 1) {
            throw new \RuntimeException('Stevnearrangør-meny skal minst ha stevner');
        }
        $lines[] = 'OK: uten cupadmin-tilgang skjules cupmenyen (UI)';

        $accountLabels = array_column(PortalV3Menu::accountLinks(), 'label');
        if (!in_array('Kom i gang', $accountLabels, true)) {
            throw new \RuntimeException('Brukermeny skal inneholde Kom i gang');
        }
        $lines[] = 'OK: brukermeny inneholder Kom i gang';

        $labels = $services->labels->resolveForSpace($space);
        $emptyArrangerHtml = $this->renderArrangerSpaceIndexSnippet($space, $labels, [
            [
                'series_id' => 1,
                'label' => 'Jaktfeltcup 2027',
                'events' => [],
                'rounds' => [
                    [
                        'series_id' => 11,
                        'label' => 'Runde 1',
                        'events' => [],
                        'create_href' => '/sesonger/11/stevner/ny',
                    ],
                    [
                        'series_id' => 12,
                        'label' => 'Runde 2',
                        'events' => [],
                        'create_href' => '/sesonger/12/stevner/ny',
                    ],
                ],
                'create_href' => null,
                'create_batch_href' => '/sesonger/1/stevner/batch',
            ],
        ], [
            'arranger_name' => 'Testklubb',
        ]);
        if (!str_contains($emptyArrangerHtml, 'Opprett stevner')) {
            throw new \RuntimeException('Arrangør sesongbolk skal ha Opprett stevner (batch)');
        }
        if (!str_contains($emptyArrangerHtml, 'Opprett for denne runden')) {
            throw new \RuntimeException('Arrangør skal ha Opprett for denne runden per runde');
        }
        if (!str_contains($emptyArrangerHtml, 'Runde 1') || !str_contains($emptyArrangerHtml, 'Runde 2')) {
            throw new \RuntimeException('Arrangør skal vise runder i sesongbolk');
        }
        if (str_contains($emptyArrangerHtml, 'Velg sesong øverst')) {
            throw new \RuntimeException('Arrangør skal ikke be om sesongvalg i header');
        }
        $lines[] = 'OK: arrangør rundevis sesongbolk med batch- og per-runde-CTA';

        $tileHtml = $this->renderArrangerSpaceIndexSnippet($space, $labels, [
            [
                'series_id' => 1,
                'label' => '2027',
                'create_href' => null,
                'create_batch_href' => '/sesonger/1/stevner/batch',
                'events' => [],
                'rounds' => [
                    [
                        'series_id' => 11,
                        'label' => 'Runde 1',
                        'create_href' => '/sesonger/11/stevner/ny',
                        'events' => [
                            [
                                'event_id' => 99,
                                'name' => 'Teststevne',
                                'starts_at' => '2027-01-01',
                                'status' => 'draft',
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'arranger_name' => 'Testklubb',
        ]);
        if (!str_contains($tileHtml, 'Påmeldinger') || !str_contains($tileHtml, 'Jaktfelt')) {
            throw new \RuntimeException('Arrangør-tiles skal ha Påmeldinger og Jaktfelt');
        }
        $lines[] = 'OK: arrangør-tiles har Påmeldinger og Jaktfelt';

        $batchHtml = $this->renderBatchFormSnippet(
            $space,
            ['series_id' => 1, 'name' => 'Jaktfeltcup 2027'],
            $labels,
            [
                [
                    'series_id' => 11,
                    'round_label' => 'Runde 1',
                    'name' => 'Testklubb – Runde 1',
                    'location_name' => '',
                    'start_date' => '',
                    'start_time' => '10:00',
                    'round_starts_on' => '2027-05-01',
                    'round_ends_on' => '2027-05-31',
                    'date_warning' => null,
                ],
            ],
        );
        if (!str_contains($batchHtml, 'Lagre stevner') || !str_contains($batchHtml, 'Runde 1')) {
            throw new \RuntimeException('Batch-skjema skal vise rader og Lagre stevner');
        }
        if (!str_contains($batchHtml, 'type="date"') || !str_contains($batchHtml, 'type="time"')) {
            throw new \RuntimeException('Batch-skjema skal ha separate dato- og tid-kontroller');
        }
        if (!str_contains($batchHtml, 'fra 01.05.2027') || !str_contains($batchHtml, 'til 31.05.2027')) {
            throw new \RuntimeException('Batch-skjema skal vise rundens datointervall');
        }
        if (\App\Support\PortalPaths::sesongStevnerBatch(1) !== '/sesonger/1/stevner/batch') {
            throw new \RuntimeException('PortalPaths::sesongStevnerBatch er feil');
        }
        $lines[] = 'OK: batch-opprett-UI og PortalPaths';

        $approvedHtml = $this->renderApplicationShowSnippet([
            'organizer_application_id' => 1,
            'application_status' => 'approved',
            'org_name' => 'Testklubb',
            'series_name' => 'Sesong 2027',
        ]);
        if (!str_contains($approvedHtml, 'Gå til mine stevner')) {
            throw new \RuntimeException('Godkjent søknad skal ha Gå til mine stevner');
        }
        $lines[] = 'OK: godkjent søknad linker til mine stevner';

        // Tilgangskontroll: space-policy krever cupadmin for edit (ikke bare meny)
        if ($services->spacePolicy->canEdit($personId, $space, $orgId) !== true) {
            throw new \RuntimeException('Cupadmin skal kunne redigere cup');
        }
        $lines[] = 'OK: spacePolicy::canEdit for cupadmin';

        $orgs = $services->organizationContext->administrableOrganizations($personId);
        $ids = array_map(static fn (array $o): int => (int) ($o['org_id'] ?? 0), $orgs);
        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException('administrableOrganizations returnerte dupliserte org_id');
        }
        $lines[] = 'OK: organisasjonsliste uten duplikate org_id';

        $brandJaktfelt = PortalCupBrand::resolve('jaktfeltcup');
        $brandNamdal = PortalCupBrand::resolve('jaktfeltkarusell-namdal');
        if (!($brandJaktfelt['resolved'] ?? false) || !($brandNamdal['resolved'] ?? false)) {
            throw new \RuntimeException('Cup-brand må resolve for jaktfeltcup og namdal');
        }
        if (($brandJaktfelt['primary_color'] ?? '') === ($brandNamdal['primary_color'] ?? '')) {
            throw new \RuntimeException('Brand-farger skal kunne være ulike mellom portaler');
        }
        $lines[] = 'OK: dynamisk cup-brand for begge portaler';

        if ($services->spaceParticipation->orgHostsEventsInSpace($orgId, $spaceId)
            || (int) ($space['owner_org_id'] ?? 0) === $orgId) {
            $lines[] = 'OK: participation/host-sjekk tilgjengelig for space';
        }

        $seriesOrganizerAccess = $this->assertSeriesOrganizerCupAccess($pdo, $services, $lines);
        if ($seriesOrganizerAccess) {
            $lines[] = 'OK: godkjent sesongarrangør uten stevne får cup-tilgang';
        }

        $payload = (new CupArrangerService($services))->listForSpace($personId, $space, $orgId);
        $lines[] = 'OK: arrangørliste i cup (count=' . count($payload['arrangers']) . ')';

        $indexHtml = $this->renderArrangersIndexSnippet($space, $payload, true);
        if (str_contains(mb_strtolower($indexHtml), 'mangler stevne')) {
            throw new \RuntimeException('Arrangør-UI skal ikke si «mangler stevne»');
        }
        if (!str_contains($indexHtml, 'Opprett stevne for ny arrangør')) {
            throw new \RuntimeException('Cupadmin skal se «Opprett stevne for ny arrangør»');
        }
        $lines[] = 'OK: cupadmin ser «Opprett stevne for ny arrangør»; ingen «mangler stevne»-tekst';

        $indexArrangerHtml = $this->renderArrangersIndexSnippet($space, $payload, false);
        if (str_contains($indexArrangerHtml, 'Opprett stevne for ny arrangør')) {
            throw new \RuntimeException('Ikke-cupadmin skal ikke se «Opprett stevne for ny arrangør»');
        }
        $lines[] = 'OK: arrangør uten create-rett ser ikke opprett-handlingen';

        $historical = null;
        foreach ($payload['arrangers'] as $row) {
            if ((bool) ($row['missing_season_event'] ?? false)) {
                $historical = $row;
                break;
            }
        }
        // Demo-data kan mangle case — simuler historisk arrangør for status-tekst.
        $historical ??= [
            'org_id' => 999001,
            'name' => 'Histor-klubb Historisk',
            'event_count' => 2,
            'season_event_count' => 0,
            'events' => [],
            'season_events' => [],
            'missing_season_event' => true,
        ];
        $statusSnippet = $this->renderArrangersIndexSnippet($space, [
            'season' => $payload['season'] ?? ['name' => 'Sesong 2026', 'season_label' => 'Sesong 2026'],
            'arrangers' => [$historical],
        ], true);
        if (str_contains(mb_strtolower($statusSnippet), 'mangler stevne')) {
            throw new \RuntimeException('Historisk status skal ikke bruke «mangler stevne»');
        }
        if (!str_contains($statusSnippet, 'Ingen stevner i')
            && !str_contains($statusSnippet, 'tidligere arrangør')) {
            throw new \RuntimeException('Historisk arrangør uten sesongstevne skal få status «Ingen stevner i …»');
        }
        $lines[] = 'OK: historisk arrangør uten stevne i valgt sesong får datadrevet status';

        $fakeSeries = [
            'series_id' => 1,
            'space_id' => (int) ($space['space_id'] ?? 0),
            'owner_org_id' => (int) ($space['owner_org_id'] ?? 0),
        ];
        if (!$services->eventPolicy->canCreateInSpace($personId, $orgId, $orgId, $fakeSeries, $space)) {
            throw new \RuntimeException('Cupadmin skal kunne canCreateInSpace');
        }
        $lines[] = 'OK: canCreateInSpace for cupadmin';

        $hierarchy = $services->series->hierarchyForSpace($personId, $spaceId, $orgId);
        $labels = $services->labels->resolveForSpace($space);
        $showHtml = $this->renderCupSeasonsSnippet(
            $space,
            $hierarchy['roots'] ?? [],
            $hierarchy['children'] ?? [],
            $labels,
            true,
            true,
            true,
        );
        if (!str_contains($showHtml, 'Struktur')) {
            throw new \RuntimeException('Cupadministrasjon skal vise struktur-lenke per sesong');
        }
        foreach ($hierarchy['roots'] ?? [] as $root) {
            if (!array_key_exists('structure_type', $root)) {
                throw new \RuntimeException('hierarchy roots skal inkludere structure_type');
            }
        }
        $eventsRoots = array_values(array_filter(
            $hierarchy['roots'] ?? [],
            static fn (array $r): bool => (string) ($r['structure_type'] ?? '') === 'events',
        ));
        if ($eventsRoots !== []) {
            $eventsHtml = $this->renderCupSeasonsSnippet($space, $eventsRoots, [], $labels, true, true, true);
            $rundeCta = 'Ny ' . strtolower($labels->singular('subseries'));
            if (str_contains($eventsHtml, $rundeCta)) {
                throw new \RuntimeException('events-struktur skal ikke vise Ny runde');
            }
            if (!str_contains($eventsHtml, $labels->plural('event'))) {
                throw new \RuntimeException('events-struktur skal vise stevner-handling');
            }
            $lines[] = 'OK: events-struktur uten Ny runde, med stevner';
        }
        $roundsRoots = array_values(array_filter(
            $hierarchy['roots'] ?? [],
            static fn (array $r): bool => (string) ($r['structure_type'] ?? '') === 'rounds',
        ));
        if ($roundsRoots !== []) {
            $roundsHtml = $this->renderCupSeasonsSnippet(
                $space,
                $roundsRoots,
                $hierarchy['children'] ?? [],
                $labels,
                true,
                true,
                true,
            );
            $rundeCta = 'Ny ' . strtolower($labels->singular('subseries'));
            if (!str_contains($roundsHtml, $rundeCta)) {
                throw new \RuntimeException('rounds-struktur skal vise Ny runde');
            }
            $lines[] = 'OK: rounds-struktur viser Ny runde';
        }
        $unsetRoots = array_values(array_filter(
            $hierarchy['roots'] ?? [],
            static fn (array $r): bool => (string) ($r['structure_type'] ?? '') === '',
        ));
        if ($unsetRoots !== []) {
            $unsetHtml = $this->renderCupSeasonsSnippet($space, $unsetRoots, [], $labels, true, true, true);
            if (!str_contains($unsetHtml, 'Sett struktur')) {
                throw new \RuntimeException('Uten struktur skal cup-UI vise «Sett struktur»');
            }
            $lines[] = 'OK: uavklart struktur viser Sett struktur';
        }
        $lines[] = 'OK: cupadministrasjon gjenspeiler sesongstruktur i UI';

        PortalV3Session::setOrganizationId($orgId);
        PortalV3Session::setSpaceId($spaceId);
        $bound = (new PortalBoundCup($services))->resolve($personId);
        if (($bound['season_options'] ?? []) === []) {
            $lines[] = 'INFO: ingen sesong-røtter i demo — sesong-UI hopper over streng sjekk';
        } else {
            if (($bound['season_series_id'] ?? null) === null && ($bound['season'] ?? null) === null) {
                throw new \RuntimeException('Valgt sesong skal resolve når sesonger finnes');
            }
            if (($bound['season_label'] ?? '') === '') {
                throw new \RuntimeException('Valgt sesong skal ha synlig label');
            }
            $lines[] = 'OK: valgt sesong resolves (label=' . $bound['season_label'] . ')';
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $space
     * @param list<array<string, mixed>> $roots
     * @param array<int, list<array<string, mixed>>> $children
     */
    private function renderCupSeasonsSnippet(
        array $space,
        array $roots,
        array $children,
        PortalEventTerminology $labels,
        bool $canEditSpace,
        bool $canManageSeries,
        bool $canCreateSeries,
    ): string {
        $can_edit_space = $canEditSpace;
        $can_manage_series = $canManageSeries;
        $can_create_series = $canCreateSeries;
        $pp = \App\Support\PortalPaths::class;
        $route_prefix = '';
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/spaces/show.php';

        return (string) ob_get_clean();
    }

    /**
     * Godkjent sesongarrangør uten krav om eksisterende stevne skal se cup/sesong.
     *
     * @param list<string> $lines
     */
    private function assertSeriesOrganizerCupAccess(PDO $pdo, PortalV3Services $services, array &$lines): bool
    {
        $stmt = $pdo->query("
            SELECT so.org_id, s.series_id, s.space_id, sp.owner_org_id,
                   sp.name AS space_name, s.name AS series_name
            FROM event_series_organizations so
            INNER JOIN event_series s ON s.series_id = so.series_id AND s.deleted_at IS NULL
            INNER JOIN event_spaces sp ON sp.space_id = s.space_id AND sp.deleted_at IS NULL
            WHERE so.relationship_type = 'organizer'
              AND so.status = 'active'
              AND so.deleted_at IS NULL
              AND so.org_id <> sp.owner_org_id
            ORDER BY so.series_organization_id
            LIMIT 1
        ");
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            $lines[] = 'INFO: ingen seriearrangør uten cup-eierskap — hoppet over cup-tilgangstest';

            return false;
        }

        $orgId = (int) ($row['org_id'] ?? 0);
        $spaceId = (int) ($row['space_id'] ?? 0);
        $seriesId = (int) ($row['series_id'] ?? 0);
        if ($orgId <= 0 || $spaceId <= 0 || $seriesId <= 0) {
            return false;
        }

        $personStmt = $pdo->prepare("
            SELECT m.person_id
            FROM org_memberships m
            INNER JOIN org_membership_roles omr ON omr.membership_id = m.membership_id
            INNER JOIN auth_roles r ON r.role_id = omr.role_id
            WHERE m.org_id = ?
              AND m.deleted_at IS NULL
              AND m.status = 'active'
              AND omr.deleted_at IS NULL
              AND omr.status = 'active'
              AND r.role_key IN ('org_owner', 'org_admin')
            ORDER BY m.membership_id
            LIMIT 1
        ");
        $personStmt->execute([$orgId]);
        $personId = (int) ($personStmt->fetchColumn() ?: 0);
        if ($personId <= 0) {
            $lines[] = 'INFO: seriearrangør-org mangler admin-person — hoppet over cup-tilgangstest';

            return false;
        }

        if (!$services->spaceParticipation->orgIsSeriesOrganizerInSpace($orgId, $spaceId)) {
            throw new \RuntimeException('orgIsSeriesOrganizerInSpace skulle være true for godkjent seriearrangør');
        }
        if (!$services->spaceParticipation->orgIsSeriesOrganizer($orgId, $seriesId)) {
            throw new \RuntimeException('orgIsSeriesOrganizer skulle være true for godkjent seriearrangør');
        }

        $space = $services->eventSpaces->findAccessible($personId, $spaceId, $orgId);
        if ($space === null) {
            throw new \RuntimeException(
                'Seriearrangør uten stevne skal få findAccessible på cup (org_id=' . $orgId
                . ', space_id=' . $spaceId . ')'
            );
        }
        if (!$services->spacePolicy->canView($personId, $space, $orgId)) {
            throw new \RuntimeException('PortalEventSpacePolicy::canView skulle være true for seriearrangør');
        }

        $series = ['series_id' => $seriesId, 'owner_org_id' => (int) ($row['owner_org_id'] ?? 0)];
        if (!$services->seriesPolicy->canView($personId, $series, $orgId)) {
            throw new \RuntimeException('PortalSeriesPolicy::canView skulle være true for seriearrangør');
        }
        if ($services->seriesPolicy->canEdit($personId, $series, $orgId)) {
            throw new \RuntimeException('Seriearrangør skal ikke kunne canEdit serie');
        }

        $access = (new PortalCupAccess($services))->forSpace($personId, $space);
        if (!in_array($orgId, $access['arranger_org_ids'] ?? [], true)) {
            throw new \RuntimeException('PortalCupAccess skal inkludere seriearrangør i arranger_org_ids');
        }
        if (!($access['is_arranger_admin'] ?? false)) {
            throw new \RuntimeException('PortalCupAccess::is_arranger_admin skulle være true');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $space
     * @param array{season: mixed, arrangers: list<array<string, mixed>>} $payload
     */
    private function renderArrangersIndexSnippet(array $space, array $payload, bool $canCreateForArranger): string
    {
        $season = $payload['season'] ?? null;
        $arrangers = $payload['arrangers'] ?? [];
        $labels = (new PortalV3Services())->labels->resolveForSpace($space);
        $route_prefix = '';
        $pp = \App\Support\PortalPaths::class;
        $can_create_for_arranger = $canCreateForArranger;
        ob_start();
        include dirname(__DIR__, 2) . '/app/02-view/portal-v3/arrangers/index.php';

        return (string) ob_get_clean();
    }

    private function assertNoPdoEventRepositories(): void
    {
        $pdoEventRepos = [
            dirname(__DIR__, 2) . '/app/05-repositories/Pdo/PdoPortalSpaceRepository.php',
            dirname(__DIR__, 2) . '/app/05-repositories/Pdo/PdoPortalSeriesRepository.php',
            dirname(__DIR__, 2) . '/app/05-repositories/Pdo/PdoPortalEventRepository.php',
        ];
        foreach ($pdoEventRepos as $path) {
            if (is_file($path)) {
                throw new \RuntimeException('PDO Event-repository finnes fortsatt: ' . basename($path));
            }
        }
    }

    private function assertApiWiring(PortalV3Services $services): void
    {
        $this->assertPrivateRepoType($services->eventSpaces, 'spaces', ApiPortalSpaceRepository::class);
        $this->assertPrivateRepoType($services->series, 'series', ApiPortalSeriesRepository::class);
        $this->assertPrivateRepoType($services->events, 'events', ApiPortalEventRepository::class);
    }

    private function assertPrivateRepoType(object $service, string $property, string $expectedClass): void
    {
        $ref = new ReflectionClass($service);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $repo = $prop->getValue($service);
        if (!$repo instanceof $expectedClass) {
            throw new \RuntimeException($expectedClass . ' forventet i ' . $ref->getName() . '::$' . $property);
        }
    }

    /**
     * @return array{space_id: int, root_id: int, round_id: int, event_id: int, space: array<string, mixed>, round: array<string, mixed>, event: array<string, mixed>}
     */
    private function loadDemoEventGraph(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM event_spaces WHERE owner_org_id = ? AND deleted_at IS NULL ORDER BY space_id LIMIT 1');
        $stmt->execute([$orgId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($space)) {
            throw new \RuntimeException('Mangler demo Event Space — kjør 001_demo_skytecuper.sql');
        }
        $spaceId = (int) ($space['space_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM event_series WHERE space_id = ? AND parent_series_id IS NULL AND deleted_at IS NULL ORDER BY series_id LIMIT 1');
        $stmt->execute([$spaceId]);
        $root = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($root)) {
            throw new \RuntimeException('Mangler demo toppserie');
        }
        $rootId = (int) ($root['series_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM event_series WHERE parent_series_id = ? AND deleted_at IS NULL ORDER BY sort_order, series_id LIMIT 1');
        $stmt->execute([$rootId]);
        $round = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($round)) {
            throw new \RuntimeException('Mangler demo underserie');
        }
        $roundId = (int) ($round['series_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM event_events WHERE series_id = ? AND deleted_at IS NULL ORDER BY event_id LIMIT 1');
        $stmt->execute([$roundId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($event)) {
            throw new \RuntimeException('Mangler demo arrangement');
        }

        return [
            'space_id' => $spaceId,
            'root_id' => $rootId,
            'round_id' => $roundId,
            'event_id' => (int) ($event['event_id'] ?? 0),
            'space' => $space,
            'round' => $round,
            'event' => $event,
        ];
    }

    private function verifyDemoCredentials(PDO $pdo, int $personId): int
    {
        $row = (new PdoPortalUserRepository($pdo))->findByEmail(self::DEMO_EMAIL);
        if ($row === null) {
            throw new \RuntimeException('Mangler auth_users for demo-bruker');
        }
        if ((int) ($row['person_id'] ?? 0) !== $personId) {
            throw new \RuntimeException('auth_users.person_id matcher ikke demo-person');
        }
        $hash = (string) ($row['password_hash'] ?? '');
        if ($hash === '' || !password_verify(self::DEMO_PASSWORD, $hash)) {
            throw new \RuntimeException('Passordverifisering feilet for demo-bruker');
        }

        return (int) ($row['user_id'] ?? 0);
    }

    private function findDemoPersonId(PDO $pdo): int
    {
        $stmt = $pdo->prepare("
            SELECT person_id FROM person_people
            WHERE legacy_source = 'events_seed' AND legacy_table = 'portal' AND legacy_id = 'skytecuper-demo-admin'
            LIMIT 1
        ");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('Mangler demo-person — kjør php modules/events/bin/console seed i bifrost-admin-core (004_demo_portal_org_admin.sql)');
        }

        return (int) $id;
    }

    private function findOtherOrgId(PDO $pdo, int $excludeOrgId): int
    {
        $stmt = $pdo->prepare("
            SELECT org_id FROM org_organizations
            WHERE legacy_source = 'events_seed' AND legacy_table = 'demo' AND org_id <> ?
            LIMIT 1
        ");
        $stmt->execute([$excludeOrgId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    /** @return array<string, mixed>|null */
    private function findEventOwnedByOrg(PDO $pdo, int $orgId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM event_events WHERE owner_org_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$orgId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
