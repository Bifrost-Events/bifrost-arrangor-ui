# bifrost-arrangor-ui

Arrangørportal for stevner, påmelding og resultater.

## Rolle i Bifrost v3 (AB-0019)

**Operativ administrasjon** av arrangementsdata skal skje her — ikke i Bifrost Admin Core.

| Område | Beskrivelse |
|--------|-------------|
| Event Spaces | De organisasjonen har tilgang til |
| Serier og underserier | Inkl. runder via hierarkisk `event_series` |
| Arrangementer | CRUD, publisering, dato, sted, kapasitet |
| Senere | Påmelding, deltakere, resultater |
| Modulspesifikt | F.eks. jaktfelt-innstillinger |

Meny og funksjoner styres av organisasjon, Event Space og brukerens roller (AB-0020). Samme portal for serieeiere og arrangementseiere. **Skjul** handlinger brukeren ikke har tilgang til.

## Tilgangsmodell (AB-0020)

| Faktor | Effekt |
|--------|--------|
| `owner_org_id` | Hvem som eier space/serie/arrangement |
| `org_owner` / `org_admin` | Administrere egne event-ressurser |
| `event_result_manager` | Resultathåndtering på tildelt arrangement |
| `event_registration_manager` | Påmelding (senere) |
| `event_viewer` | Lesetilgang til tildelt arrangement |

Tildeling via `event_person_roles` — uten org-medlemskap (AB-0021).

Serieeier og arrangementseier er **organisasjoner** — ikke brukerroller. Policy-lag i `bifrost-events` — se [organizer-portal-authorization.md](../bifrost-docs/architecture/organizer-portal-authorization.md).

## Avhengigheter

- **bifrost-events** — domenemodul (tabeller, API, migreringer)
- **bifrost-admin-core** — plattformkjerne (org, personer, brukere, medlemskap, roller)

Admin Core håndterer plattformoppsett; denne portalen håndterer daglig arrangementsarbeid.

## Dokumentasjon

- [AB-0020](../bifrost-docs/decisions/AB-0020-organizer-portal-authorization.md)
- [AB-0021](../bifrost-docs/decisions/AB-0021-event-person-roles.md)
- [event_person_roles.md](../bifrost-docs/architecture/entities/event_person_roles.md)
- [organizer-portal-authorization.md](../bifrost-docs/architecture/organizer-portal-authorization.md)
- [events-authorization.md](../bifrost-events/docs/events-authorization.md)
- [repo-and-admin-structure.md](../bifrost-docs/architecture/repo-and-admin-structure.md)
- [docs/v3-organizer-portal-transition.md](docs/v3-organizer-portal-transition.md)
- [events-module.md](../bifrost-events/docs/events-module.md)

## Status

Legacy v2-flyt mot eldre backend-API (`/stevner`) finnes fortsatt parallelt.

**V3 arrangørportal** er standard på `/portal-v3/` — krever ikke ekstra env utover vanlig `.env`. Events API-URL utledes fra `BACKEND_URL` (`http://api.*` → `http://admin.*`). Database bruker samme defaults som admin-core.

```bash
php bin/console smoke-test
php bin/console check-v2-routes
```

Se [docs/v3-organizer-portal-transition.md](docs/v3-organizer-portal-transition.md).
