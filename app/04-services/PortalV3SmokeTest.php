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

        $lines[] = 'INFO: kjør «php bin/console organizer-api-test» i bifrost-events for live API-verifisering';
        $lines[] = 'Alle Portal V3 smoke-tester bestått (API-only Events).';

        return $lines;
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
        $lines[] = 'OK: cupadmin-meny inneholder Cupadministrasjon og Arrangører';

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
        $lines[] = 'OK: uten cupadmin-tilgang skjules cupmenyen (UI)';

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
            throw new \RuntimeException('Mangler demo-person — kjør php bin/console seed i bifrost-events (004_demo_portal_org_admin.sql)');
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
