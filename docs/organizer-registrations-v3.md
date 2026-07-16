# Arrangørportal — V3-påmeldinger

**Status:** Implementert  
**Dato:** 2026-07-16  
**API:** [../bifrost-events/docs/organizer-registrations-v3.md](../../bifrost-events/docs/organizer-registrations-v3.md)

---

## UI

| Side | URL |
|------|-----|
| Liste | `/stevner/{eventId}/pameldinger` |
| Manuell | `/stevner/{eventId}/pameldinger/ny` |
| Detalj | `/stevner/{eventId}/pameldinger/{registrationId}` |
| CSV | `/stevner/{eventId}/pameldinger/export` |

Tilgang styres av `PortalEventPolicy::canManageRegistrations` (speiler events).  
Lenke «Påmeldinger» på arrangementsredigering.

---

## Tester

```bash
cd ../bifrost-events
php bin/console organizer-registrations-test
php bin/console public-registrations-test

cd ../bifrost-arrangor-ui
php bin/console check-v2-routes
```
