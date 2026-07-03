<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\BackendApiClient;

/** Cup/sesong-profil for arrangørportalen (uten innlogging). */
final class PortalProfile
{
    /**
     * @return array{
     *   resolved: bool,
     *   cup_name: string,
     *   season_label: string,
     *   register_url: string,
     *   host: string,
     *   error: string|null
     * }
     */
    public static function current(?BackendApiClient $client = null): array
    {
        $tenantCtx = TenantContext::current();
        $host = (string) ($tenantCtx['host'] ?? '');
        $cupName = trim((string) ($tenantCtx['display_name'] ?? ''));
        $error = $tenantCtx['error'] ?? null;
        $registerUrl = (string) Config::get('app.public_register_url', '');

        if (!($tenantCtx['resolved'] ?? false)) {
            return [
                'resolved' => false,
                'cup_name' => $cupName !== '' && $cupName !== 'Cup' ? $cupName : '',
                'season_label' => '',
                'register_url' => $registerUrl,
                'host' => $host,
                'error' => is_string($error) ? $error : 'Kunne ikke finne cup for dette domenet.',
            ];
        }

        $client ??= new BackendApiClient();
        $resolve = $client->resolveTenant($host);
        if ($resolve['ok'] && is_array($resolve['data'] ?? null)) {
            $urls = is_array($resolve['data']['urls'] ?? null) ? $resolve['data']['urls'] : [];
            $fromTenant = trim((string) ($urls['public_register'] ?? ''));
            if ($fromTenant !== '') {
                $registerUrl = $fromTenant;
            }
        }

        $calendar = $client->publicCalendar($host);
        if ($calendar['ok'] && is_array($calendar['data'] ?? null)) {
            $data = $calendar['data'];
            $tenant = is_array($data['tenant'] ?? null) ? $data['tenant'] : [];
            $season = is_array($data['season'] ?? null) ? $data['season'] : null;
            if ($cupName === '' || $cupName === 'Cup') {
                $cupName = trim((string) ($tenant['name'] ?? ''));
            }

            return [
                'resolved' => true,
                'cup_name' => $cupName,
                'season_label' => self::formatSeasonLabel($season),
                'register_url' => $registerUrl,
                'host' => $host,
                'error' => null,
            ];
        }

        return [
            'resolved' => true,
            'cup_name' => $cupName,
            'season_label' => '',
            'register_url' => $registerUrl,
            'host' => $host,
            'error' => (string) ($calendar['error'] ?? null) ?: null,
        ];
    }

    /** @param array<string, mixed>|null $season */
    public static function formatSeasonLabel(?array $season): string
    {
        if ($season === null) {
            return '';
        }

        $name = trim((string) ($season['name'] ?? ''));
        $year = (int) ($season['year'] ?? 0);
        if ($name !== '' && ($year <= 0 || str_contains($name, (string) $year))) {
            return $name;
        }
        if ($name !== '' && $year > 0) {
            return $name . ' ' . $year;
        }
        if ($year > 0) {
            return (string) $year;
        }

        return $name;
    }
}
