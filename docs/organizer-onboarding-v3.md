# Arrangørportal — onboarding V3

**Status:** Implementert  
**Dato:** 2026-07-20  
**App:** `bifrost-arrangor-ui` (V3 portal)

---

## Meny

| Meny | Rute | Rolle |
|------|------|-------|
| Kom i gang | `/kom-i-gang` | Gjester (opprett konto) + innloggede |
| Mine organisasjoner | `/mine-organisasjoner` | Søker |
| Arrangørsøknader | `/arrangor-soknader` | Søker (org_owner/admin) |
| Søknader om stevne | `/sesonger/{id}/arrangor-soknader` | Serieeier (cup-admin + sesong) |

**Innloggingsside:** Lenke «Ny arrangør uten konto? Kom i gang».

Uten administrerbar org (etter innlogging): redirect til `/kom-i-gang`.

---

## Styrt veiviser (`/kom-i-gang`)

Lineær flyt med stegindikator:

1. **Konto** — opprett bruker (hvis ikke innlogget)
2. **Organisasjon** — velg eller opprett
3. **Cup** — kun når portalen **ikke** er domenbundet
4. **Serie** — åpne serier filtrert på `application_id`
5. **Søknad** — detaljer, lagre utkast eller send inn

`GET /arrangor-soknader/ny` redirecter inn i veiviseren (ingen parallell «alle serier»-liste).

### Domenefilter

- Domenbundet host (`app_domains`) → kun serier for den applikasjonen
- Uten binding → brukeren velger cup først, deretter serier i den cupen

API: `GET /api/organizer/organizer-onboarding/series?application_id=&org_id=&space_id=`

---

## Tester

```bash
php bin/console smoke-test   # bifrost-arrangor-ui
php bin/console organizer-onboarding-test  # bifrost-events
```

Dekker: login-lenke, veiviser-steg, cup-avgrenset serieliste, register, `application_id`-filter.

---

## Søkerflyt

1. **Opprett konto** (første del av «Kom i gang»)
2. Opprett / velg organisasjon
3. (Evt.) velg cup
4. Velg sesong (radio-liste) i aktuell cup; allerede godkjente sesonger vises separat
5. Valgfri melding → lagre utkast / send inn → se status / trekk

Søknaden gjelder **hele sesongen**. Stevnenavn/dato angis ikke; etter godkjenning oppretter arrangøren stevner fritt.

## Serieeierflyt

1. Liste søknader (filter status)
2. Sett under behandling
3. Godkjenn / avslå med `review_notes`

---

## API-klient

`EventsApiClient` — org, søknader, approve/reject, settings, `listOnboardingSeries(..., applicationId|applicationKey)`.  
Kontoopprettelse via `PortalV3AuthService::register`.

Domenebundet portal filtrerer sesonger på `application_key` (ikke lokal `application_id`), slik at Events API og portal-DB kan ha ulike numeriske id-er.

Session: `onboarding_application_id`, `onboarding_org_id`, `onboarding_series_id`.

---

## Relatert

- `bifrost-events/docs/organizer-onboarding-v3.md`
- Legacy `/bli-arrangor` brukes ikke når V3 portal er aktiv
