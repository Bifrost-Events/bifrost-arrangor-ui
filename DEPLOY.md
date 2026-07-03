# Deploy test (multi-cup)

Arrangørportalen deployes via GitHub Actions (`deploy-release.yml`) til ProISP. Én deploy betjener flere cup-tenants via HTTP-host.

## Test-domener

| Cup | URL |
|-----|-----|
| Jaktfeltcup | https://test.arrangor.jaktfeltcup.no |
| Namdal | https://test.arrangor.namdal.jaktfeltkarusell.no |

## Før første deploy

1. Kjør migrasjon `bifrost_018_arrangor_test_domains.sql` på test-DB.
2. Opprett ProISP filområde → `.../bifrostarrangorui/public/` som document root.
3. Pek begge DNS-hosts til samme webroot (vhost-aliaser).
4. Oppdater `PLACEHOLDER_TEST_ARRANGOR` i Deploy-Admin og `release/config/deploy-secrets.local.yml`.
5. `npm run release:sync-secrets` (fra bifrost-public-ui).
6. Legg `.env` på server (kopier fra `.env.test.example` – ikke i git/FTP).
7. Backend/public test-`.env`: valgfri `ARRANGOR_PORTAL_URL` fallback (se `.env.test.example`).
8. Fjern `trackOnly: true` under `arrangor-ui` i `bifrost-public-ui/release/config/repos.yml`.

## Deploy

```powershell
# Fra bifrost-public-ui etter release:create + quality-godkjenning
npm run release:deploy -- -ReleaseId <id> -Environment test
```

Eller manuelt:

```bash
gh workflow run deploy-release.yml -R Bifrost-Events/bifrost-arrangor-ui \
  -f environment=test -f release_id=<id> -f ref=<sha>
```

Smoke bruker `APP_URL=https://test.arrangor.jaktfeltcup.no` fra GitHub Environment `test`.

## Verifikasjon

- `GET /health` på begge domener → `arrangor_ui`
- Login viser cup/sesong per host
- `npm run quality:local` med `arrangor-jaktfeltcup` og `arrangor-namdal` manifests
