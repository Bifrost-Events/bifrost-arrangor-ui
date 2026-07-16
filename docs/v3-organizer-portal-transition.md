# V3-overgang — arrangørportal

**Status:** Fullført (V3 API-only)  
**Dato:** 2026-07-14

## Formål

Kartlegge eksisterende arrangørportal (Jaktfeltkarusell Namdal v2 + `bifrost-arrangor-ui`) og beskrive overgang til Bifrost v3 core- og event-datamodell.

**Prinsipp:** Gjenbruk fungerende portalstruktur og UX. Ikke bygg om alt på én gang. Ikke slett v2 før ny flyt er verifisert.

Beslutningsgrunnlag: [AB-0019](../../bifrost-docs/decisions/AB-0019-events-administration-in-organizer-portal.md), [AB-0020](../../bifrost-docs/decisions/AB-0020-organizer-portal-authorization.md), [AB-0021](../../bifrost-docs/decisions/AB-0021-event-person-roles.md).

---

## To kodebaser

| Repo | Rolle i dag | V3-mål |
|------|-------------|--------|
| **`bifrost-arrangor-ui`** | Dedikert arrangørportal — tynn klient mot `bifrost-backend` API | Primær V3-portal; ny flyt under `/portal-v3/` |
| **`jaktfeltnamdalen`** | V2 monolitt — arrangør-UI innebygd i `/dashboard?tab=stevner` | Referanse for UX og flyt; **ikke slettes** før V3 er verifisert |

`bifrost-arrangor-ui` har **ingen PDO-repositories** i v2-flyten — all persistens går via `BackendApiClient` → `bifrost-backend` (`/api/organizer/*`).

---

## bifrost-arrangor-ui — v2-flyt (eksisterende)

### Entry points

| Fil | Rolle |
|-----|-------|
| `public/index.php` | Front controller |
| `public/cli-server-router.php` | PHP built-in server |
| `app/06-support/bootstrap.php` | Autoload, config (`app`, `backend`) |
| `routes/web.php` | Alle HTTP-ruter |

### Session og auth

| Element | Verdi |
|---------|-------|
| Session-navn | `BIFROSTARRANGOR` |
| Auth-nøkkel | `bifrost_arrangor_auth` |
| Backend-cookie | `bifrost_backend_cookie` (proxy for `BIFROSTSESSID`) |
| Org i session | `bifrost_arrangor_org_id` |
| Sesong i session | `bifrost_arrangor_season_id` |

**Flyt:**

1. `LoginController` → `BackendApiClient::participantLogin()` → `/api/auth/participant/login`
2. Bruker lagres i session; backend-session-cookie lagres for videre API-kall
3. `Auth::requireOrganizer()` sjekker `AuthService::canAccessOrganizer()` (medlemskap / `can_access_organizer`)
4. `Auth::requireLogin()` krever backend-cookie — uten den tømmes session

### Aktiv arrangør og sesong

`ArrangorView::resolveOrganizerContext()`:

1. `?organization_id=` / `?season_id=` → session
2. `TenantContext::current()` fra HTTP-host → `tenant_id`, `portal_host`
3. `GET /api/organizer/context` → kanonisk org, sesong, runder, `can_write`, `approval`
4. Session oppdateres med resolved IDs

**v2-begrep i API:** `organization`, `season`, `rounds`, `competitions` — ikke Bifrost v3-tabellnavn.

### Routes (v2)

| Område | Prefix | Controller |
|--------|--------|------------|
| Auth | `/login`, `/logout` | `LoginController` |
| Dashboard | `/` | `DashboardController` |
| Onboarding | `/bli-arrangor/*` | `OnboardingController` |
| Stevner | `/stevner/*` | `CompetitionsController`, `CompetitionsStevneAdminController` |
| Deltakere | `/deltakere` | `ParticipantsController` |
| Organisasjon | `/min-organisasjon`, `/organisasjon/*` | `OrganizationController` |

Full liste: `routes/web.php`. Meny: `config/arrangor-menu.php`.

### Services (v2)

| Klasse | Rolle |
|--------|-------|
| `BackendApiClient` | All HTTP mot backend |
| `AuthService` | `canAccessOrganizer()` |

**Ingen** repositories, policies eller use-case-lag i v2-flyten. Validering inline i controllers.

### Views (gjenbrukbar for V3)

| Fil | Gjenbruk |
|-----|----------|
| `app/02-view/arrangor/layout.php` | Sidebar, toppbar, org-velger — **basis for V3-layout** |
| `app/02-view/arrangor/competitions/form.php` | Skjemaoppsett for arrangement |
| `app/02-view/arrangor/competitions/list.php` | Listevisning |
| `app/02-view/arrangor/dashboard.php` | Oversikt |

**Ikke gjenbruk i første V3-slice:** stevneadmin, påmelding, resultater (`stevneadmin-*`, `PameldelseViewData`, `StevneAdminViewData`).

### v2 API-avhengighet (backend)

Alle kall i `app/04-services/BackendApiClient.php` mot `BACKEND_URL` — tabeller på backend-siden er **ikke** v3 `event_*`. Overgangen kobler V3-flyt direkte mot shared database / fremtidig `bifrost-events` API.

---

## jaktfeltnamdalen — v2 monolitt (referanse)

### Entry

`public/index.php` → `routes/web.php` (245+ linjer). Arrangørportal = **`/dashboard?tab=stevner`**.

### Arrangør-state (query-param, ikke session)

| Param | Betydning |
|-------|-----------|
| `tab=stevner` | Arrangørseksjon |
| `organizer_id` | Aktiv arrangør |
| `competition_id` | Aktivt stevne |
| `arranger_view` | `oversikt`, `stevner`, `nytt_stevne`, `pameldelse`, `gjennomfor`, … |
| `stevner_sub` | Underfane |

Hovedview: `app/02-view/stevner-content.php` (3200+ linjer).

### Auth og bruker ↔ arrangør

- `App\Support\Auth` → OAuth / local via `ServiceFactory`
- **Kobling:** `jaktfelt_organizer_members` (`organizer_id`, `user_id`, `role`)
- Roller: `OWNER`, `ADMIN`, `REGISTRAR`, `VIEWER`
- `PdoOrganizerRepository::listByUserId()` — arrangører for innlogget bruker

Parallelt nyere org-modell: `jaktfelt_organization_memberships` + `jaktfelt_organization_membership_roles` + `jaktfelt_app_roles`.

### Sesong, runde, stevne (v2-tabeller)

| v2-begrep | Tabell | Repository |
|-----------|--------|------------|
| Sesong | `jaktfelt_seasons` | `PdoSeasonRepository` |
| Runde | `jaktfelt_rounds` | `PdoRoundRepository` |
| Stevne | `jaktfelt_competitions` | `PdoCompetitionRepository` |
| Arrangør | `jaktfelt_organizers_v2` | `PdoOrganizerRepository` |
| Arrangørmedlem | `jaktfelt_organizer_members` | (via organizer repo) |

**Lasting:** `SeasonController::getIndexData()` — tre sesong → runder → stevner, filtrert på `memberOrganizerIds` og `DomainCupResolver`.

### v2-direkte koblinger (skal **ikke** kopieres til V3)

- `jaktfelt_competition_signup_slots`, `jaktfelt_competition_signup_figures`
- `jaktfelt_competition_results`, `jaktfelt_participants`
- `jaktfelt_competition_signup_*` (påmelding)
- `CompetitionAppsController` / stevneadmin
- `DomainCupResolver` / `domain_host` på sesong
- `InstallationProfile::feature()` (økonomi, arkiv, …)

### v2 generelt gjenbrukbart

| Område | Mønster |
|--------|---------|
| Lagdeling | `02-view` / `03-controller` / `04-services` / `05-repositories` / `06-support` |
| DI | `Container.php` composition root |
| Use cases | `CreateOrganizerUseCase`, … |
| Auth actions | `AuthorizationService::can()` — **erstattes av policy-lag i V3** |
| Validering | Inline + `CompetitionLimits` |
| Feature flags | `config/installations/namdal.php` → `InstallationProfile::feature()` |

---

## V3 målmodell

### Core (shared database)

`person_people`, `auth_users`, `org_organizations`, `org_memberships`, `org_membership_roles`, `auth_roles`, `app_applications`, `app_domains`

### Events

`event_spaces`, `event_series`, `event_events`, `event_person_roles` (når implementert)

### Mapping v2 → v3

| v2 | v3 | Merknad |
|----|-----|---------|
| arrangør | `org_organizations` | Aktiv kontekst = organisasjon |
| arrangørmedlem | `org_memberships` + `org_membership_roles` | |
| sesong | `event_series` (`series_type = season`, rot) | |
| runde | `event_series` (`series_type = round`, `parent_series_id`) | |
| stevne | `event_events` | |
| aktiv arrangør | aktiv organisasjon (session) | Ikke `organizer_id` i ny kode |
| tenant/cup host | `app_applications` / `app_domains` | Senere; ikke kritisk i slice 1 |
| `domain_host` på sesong | `event_spaces.application_id` | Applikasjon via space |

**Ikke bruk** `organizer`, `season`, `round`, `competition` som interne klassenavn i V3 — bruk `organization`, `series`, `event`, `event_space`.

UI-labels via `EventLabelResolver` / `ui_labels_json` (jaktfelt: Cup/Sesong/Runde/Stevne).

---

## Første vertikale V3-flyt

Implementert under **`/portal-v3/`** når `ORGANIZER_PORTAL_V3_ENABLED=true`.

| Steg | Beskrivelse |
|------|-------------|
| 1 | Innlogget bruker (`auth_users` → `person_id`) |
| 2 | Finn org med `org_owner` / `org_admin` |
| 3 | Velg aktiv organisasjon (session) |
| 4 | Vis Event Spaces med `owner_org_id = aktiv org` |
| 5 | Åpne space → toppserier + underserier |
| 6 | Velg serie → arrangementer |
| 7 | Opprett/rediger arrangement når `owner_org_id` = aktiv org |

**Ikke i slice 1:** påmelding, resultater, stevneadmin, `event_person_roles`, migrering av v2-data.

### Tjenestelag (V3)

| Klasse | Rolle |
|--------|-------|
| `OrganizationContextService` | Person → org med admin-roller; aktiv kontekst |
| `EventSpaceService` | Spaces for aktiv org |
| `SeriesService` | Hierarki per space |
| `EventService` | CRUD arrangement i serie |
| `EventSpacePolicy` | `canView`, `canEdit` |
| `SeriesPolicy` | `canView`, `canEdit` |
| `EventPolicy` | `canView`, `canCreate`, `canEdit` |

### Aktiv kontekst (session)

| Nøkkel | Innhold |
|--------|---------|
| `portal_v3_org_id` | Aktiv organisasjon |
| `portal_v3_space_id` | Aktivt Event Space |

Organisasjon velges eksplisitt — ikke utledet fra domene alene.

### Overgangsstrategi

| Mekanisme | Verdi |
|-----------|-------|
| Feature flag | `ORGANIZER_PORTAL_V3_ENABLED=true` |
| Route-prefix | `/portal-v3/*` |
| v2-flyt | Eksisterende `/`, `/stevner`, … uendret |
| Database | `DB_DSN` / `DB_USER` / `DB_PASS` — samme som `bifrost-admin-core` |
| Auth V3 | Direkte PDO mot `auth_users` (ikke backend-cookie) |

---

## Verifikasjonsscenario

**Status:** Verifisert 2026-07-14 (lokal `bifrost_admin_core`)

### Seed-data brukt

| Entitet | Kilde | Legacy-nøkkel | ID (lokal DB) |
|---------|-------|---------------|---------------|
| `app_applications` (Skytecuper) | `bifrost-events/database/seeds/001_demo_skytecuper.sql` | `application_key=skytecuper` | `application_id=5` |
| `org_organizations` | `001_demo_skytecuper.sql` | `events_seed/demo/skytecuper-org` | `org_id=80` |
| `event_spaces` | `001_demo_skytecuper.sql` | `events_seed/demo/skytecuper-space` | `space_id=1` |
| Toppserie (`series_type=season`) | `001_demo_skytecuper.sql` | `namdalscup-2027` | `series_id=1` |
| Underserie (`series_type=round`) | `001_demo_skytecuper.sql` | `namdalscup-2027-round-1` | `series_id=2` |
| `event_events` | `001_demo_skytecuper.sql` | `grong-stevnet` | `event_id=1` |
| `person_people` | `bifrost-events/database/seeds/004_demo_portal_org_admin.sql` | `events_seed/portal/skytecuper-demo-admin` | `person_id=400` |
| `auth_users` | `004_demo_portal_org_admin.sql` | `events_seed/portal/skytecuper-demo-admin` | `user_id=400` |
| `org_memberships` | `004_demo_portal_org_admin.sql` | `events_seed/portal/skytecuper-demo-membership` | aktiv |
| `org_membership_roles` | `004_demo_portal_org_admin.sql` | `org_admin` | aktiv |

**Demo-innlogging:** `skytecuper-admin@demo.bifrost.local` / `Demo123!`

**Negativ testdata (annen org):** Fotballcup-demo fra `003_demo_fotballcup.sql` (`org_id=81`).

**Manglet før verifikasjon:** `004_demo_portal_org_admin.sql` — person, auth-bruker og `org_admin`-medlemskap mot Skytecuper-demo-org. Opprettet idempotent; ingen duplikater ved re-seed.

### Seed-kjøring (idempotent)

```text
cd bifrost-admin-core
php bin/console migrate
php bin/console seed          # 001_standard_roles, 002_applications

cd ../bifrost-events
php bin/console migrate
php bin/console seed          # 001–004 (inkl. ny portal-bruker)
```

### Tilgang — policy-bruk (oppgave 2)

Policies brukes via service-lag — ikke hardkodet i views/controllers:

| Lag | Sjekk |
|-----|-------|
| `OrganizationPolicy::canAdministerOrganization` | Aktivt medlemskap + `org_owner`/`org_admin` via `PdoPortalMembershipRepository` |
| `EventSpacePolicy` | `owner_org_id === activeOrgId` + org-admin |
| `SeriesPolicy` | `owner_org_id === activeOrgId` + org-admin |
| `EventPolicy` | `owner_org_id === activeOrgId` + org-admin for view/create/edit |

Controllers kaller `*Service::findAccessible()` / `listForOrganization()` / `hierarchyForSpace()` som filtrerer via policies. `V3EventController` bruker `eventPolicy->canEdit` / `canCreate` for skjema og lagring.

**Merk:** `ADMIN_ROLE_KEYS` er midlertidig hardkodet i `PdoPortalMembershipRepository` og `OrganizationPolicy` (teknisk gjeld).

### Automatiske tester (oppgave 3)

| Kommando | Repo | Resultat |
|----------|------|----------|
| `php bin/console smoke-test` | `bifrost-events` | **Bestått** — tabeller, space, seriehierarki, arrangement |
| `php bin/console smoke-test` | `bifrost-arrangor-ui` | **Bestått** — se punkter under |
| `php bin/console check-v2-routes` | `bifrost-arrangor-ui` | **Bestått** — `/`, `/stevner` + V3-ruter registrert |
| `php bin/console check-seed` | `bifrost-arrangor-ui` | Informasjon — radtelling i seed-tabeller |

**Portal V3 smoke-test dekker:**

- `org_admin` får tilgang til egne ressurser (org, space, serie, arrangement)
- `EventPolicy::canEdit` true for eget arrangement
- Avslag på arrangement og Event Space eid av Fotballcup-demo-org (`org_id=81`)
- Seriehierarki (root + round med `parent_series_id`)
- Terminologi fra `ui_labels_json` (`Stevne` / `Sesonger`)
- Passordverifisering for demo-bruker (uten session i CLI)

**v2-ruter:** `/`, `/stevner`, `/login`, `/health` registrert uavhengig av `ORGANIZER_PORTAL_V3_ENABLED`. V3-ruter registreres kun når flagget er `true` (som forventet).

**Manuell smoke (anbefalt etter deploy):**

```text
1. Sett ORGANIZER_PORTAL_V3_ENABLED=true og DB_* i .env
2. php -S localhost:8084 -t public public/cli-server-router.php
3. /portal-v3/login — logg inn med demo-bruker
4. Velg Skytecuper Demo Org → spaces → serie → rediger Grong-stevnet
5. Bekreft at v2 / og /stevner fortsatt laster (krever BACKEND_URL)
```

### Rettelser under verifikasjon

| Problem | Løsning |
|---------|---------|
| Manglende demo-bruker med `org_admin` på Skytecuper-org | Ny seed `004_demo_portal_org_admin.sql` |
| Ingen automatisert V3-test | `PortalV3SmokeTest` + `bin/console smoke-test` |
| Seed-status vanskelig å sjekke | `bin/console check-seed` |

### Gjenstående teknisk gjeld

- `ADMIN_ROLE_KEYS` hardkodet (bør hentes fra `auth_roles` eller config)
- Ingen HTTP/E2E-test av full UI-flyt i API-modus (service smoke + manuell test)
- `event_person_roles`, påmelding, resultater, stevneadmin — utenfor scope

---

## Full V3-overgang (slice 3) — fullført

**Status:** Fullført 2026-07-14

### Hva er gjort

| Område | Endring |
|--------|---------|
| Dataflyt | All Event-data i V3-portalen går via `EventsApiClient` → `/api/organizer/*` |
| PDO-fallback | Fjernet `PdoPortal{Space,Series,Event}Repository`, `V3_DATA_SOURCE`, `PortalV3::usesApi()` |
| Cup-/serieadmin | `V3SeriesController`, space-redigering, underserier, `sort_order`, arkivering |
| Arrangementsadmin | CRUD + arkivering via API; serieeier ser arrangementer (inkl. andre orgs) |
| Meny | Kontekststyrt `PortalV3Menu` — ingen separate «Cup-admin»/«Arrangør»-menyer |
| Policies | Utvidet `EventSpacePolicy`, `SeriesPolicy`, `EventPolicy` (inkl. `canViewEvents`, arkivering) |
| API | Arkivering (`POST .../archive`), `GET .../event-spaces/{id}/events` |

### Fjernet fallback-kode

- `app/05-repositories/Pdo/PdoPortalSpaceRepository.php`
- `app/05-repositories/Pdo/PdoPortalSeriesRepository.php`
- `app/05-repositories/Pdo/PdoPortalEventRepository.php`
- `config/v3.php` → `data_source` / `V3_DATA_SOURCE`
- `PortalV3::usesApi()`, `PortalV3::dataSource()`

**Beholdt PDO for core:** `PdoPortalUserRepository`, `PdoPortalMembershipRepository` (auth/org).

### Aktivering

V3 er **på som standard**. Ingen ekstra env er nødvendig utover vanlig arrangør-`.env` (`BACKEND_URL`, `APP_*`).

| Oppsett | Kilde |
|---------|-------|
| V3-ruter | `ORGANIZER_PORTAL_V3_ENABLED` default `true` — sett `false` for å deaktivere |
| Events API | Utledes fra `BACKEND_URL`: `http://api.*` → `http://admin.*`; overstyres med `EVENTS_URL` |
| Core DB | `DB_DSN` / `DB_USER` / `DB_PASS` — standard local default i `config/database.php` |

```env
# Valgfritt
# ORGANIZER_PORTAL_V3_ENABLED=false
# EVENTS_URL=http://admin.bifrost.local
```

### Tester

| Kommando | Resultat |
|----------|----------|
| `bifrost-events: php bin/console organizer-api-test` | **Bestått** |
| `bifrost-arrangor-ui: php bin/console smoke-test` | **Bestått** (API-wiring + policy + demo-data) |
| `bifrost-arrangor-ui: php bin/console check-v2-routes` | **Bestått** |

### Bevisst utsatt

- Påmelding, resultater, stevneadmin
- `event_person_roles` og full permission-tabellmodell
- V2-datamigrering
- Synlighet av underserier for arrangører som ikke eier serien (cross-org påmelding)

---

## PDO → API-overgang (slice 2) — historikk

**Status:** Implementert 2026-07-14

### bifrost-events — nytt API

Endepunkter under `/api/organizer/*` med policy-lag, validering og JSON-respons. Se [events-api.md](../../bifrost-events/docs/events-api.md).

| Komponent | Sti |
|-----------|-----|
| API-controller | `app/03-controller/Api/OrganizerApiController.php` |
| Services | `app/04-services/Organizer/*`, `OrganizerServices.php` |
| Policies | `app/04-services/Policy/*` |
| Ruter | `routes/module-api.php` |
| Smoke-test | `php bin/console organizer-api-test` |

### bifrost-arrangor-ui — API-klient

| Trinn | Endring | Status |
|-------|---------|--------|
| A — Event Spaces | `ApiPortalSpaceRepository` + `EventsApiClient` | **Implementert** |
| B — Serier | `ApiPortalSeriesRepository` (hierarki) | **Implementert** |
| C — Arrangementer | `ApiPortalEventRepository` (liste/detalj/CRUD) | **Implementert** |

Aktivering (historisk slice 2 — erstattet av API-only i slice 3):

```env
ORGANIZER_PORTAL_V3_ENABLED=true
EVENTS_URL=http://admin.bifrost.local
DB_DSN=...
```

**Auth:** V3-login synkroniserer `BIFROSTADMIN`-session (`AdminSessionBridge`). API-kall sender cookien videre.

**Uendret:** v2 `/stevner`, core-medlemskap via PDO, `OrganizationContextService`.

### Tester (API slice)

| Kommando | Resultat |
|----------|----------|
| `bifrost-events: php bin/console organizer-api-test` | **Bestått** |
| `bifrost-arrangor-ui: php bin/console smoke-test` | **Bestått** |

Se **Full V3-overgang (slice 3)** for gjeldende arkitektur.

---

## Verifikasjonsscenario (opprinnelig plan)

Forutsetter seed fra `bifrost-events` (f.eks. `001_demo_skytecuper.sql`) og medlemskap i `bifrost-admin-core`.

| # | Forutsetning |
|---|--------------|
| 1 | Person med `auth_users.person_id` |
| 2 | `org_memberships` + `org_membership_roles` med `org_admin` eller `org_owner` |
| 3 | `event_spaces.owner_org_id` = den org |
| 4 | Rot-serie (`parent_series_id` NULL) i space |
| 5 | Underserie (`series_type = round`) under rot-serie |
| 6 | `event_events` med `series_id` = underserie, `owner_org_id` = org |

### Teststeg

```text
1. Sett ORGANIZER_PORTAL_V3_ENABLED=true og DB_* i .env
2. Kjør migrate + seed i bifrost-admin-core og bifrost-events
3. Opprett/koble bruker med org_admin på demo-org (seed eller admin-core UI)
4. php -S localhost:8084 -t public public/cli-server-router.php
5. Åpne /portal-v3/login — logg inn
6. Velg organisasjon (hvis flere)
7. /portal-v3/spaces — se space eid av org
8. Åpne space — se sesong + runde (labels fra ui_labels_json)
9. Åpne underserie — se arrangement
10. Rediger arrangement — skal fungere
11. Forsøk åpne space/arrangement eid av annen org — skal avvises (403/tom liste)
```

### Negativ test

- Bruker uten `org_admin`/`org_owner` → ingen administrerbare org → melding, ikke data
- `EventPolicy::canEdit` false for annen orgs `owner_org_id` → ingen rediger-knapper

---

## Filindeks V3 (nye filer)

| Sti | Rolle |
|-----|-------|
| `config/v3.php` | Feature flag |
| `config/database.php` | PDO-config |
| `routes/portal-v3.php` | V3-ruter |
| `app/04-services/OrganizationContextService.php` | Org-kontekst |
| `app/04-services/EventSpaceService.php` | Spaces |
| `app/04-services/SeriesService.php` | Serier |
| `app/04-services/EventService.php` | Arrangementer |
| `app/04-services/EventLabelResolver.php` | Terminologi |
| `app/04-services/Policy/*.php` | Policies |
| `app/05-repositories/Pdo/PdoPortal{User,Membership}Repository.php` | Core auth/org |
| `app/05-repositories/Api/*.php` | API-adapters (spaces, series, events) |
| `app/03-controller/V3/V3SeriesController.php` | Cup-/serieadmin |
| `app/06-support/PortalV3Menu.php` | Kontekststyrt meny |
| `app/04-services/PortalV3SmokeTest.php` | Automatisert V3 smoke-test (API-only Events) |
| `app/06-support/AdminSessionBridge.php` | Synk V3-login → BIFROSTADMIN |
| `bin/console` | CLI: `smoke-test`, `check-v2-routes`, `check-seed` |

---

## Neste steg

1. `event_person_roles` + utvidet `EventPolicy`
2. Application/domain-kontekst fra `app_domains`
3. Erstatte v2 `/stevner` med redirect til `/portal-v3` når feature flag globalt
4. Påmelding og resultater (egne slices)
5. Migrering jaktfeltnamdalen v2 → v3 data

---

## Relatert dokumentasjon

- [README.md](../README.md)
- [bifrost-events/docs/events-module.md](../../bifrost-events/docs/events-module.md)
- [bifrost-events/docs/events-authorization.md](../../bifrost-events/docs/events-authorization.md)
- [bifrost-docs/architecture/organizer-portal-authorization.md](../../bifrost-docs/architecture/organizer-portal-authorization.md)
