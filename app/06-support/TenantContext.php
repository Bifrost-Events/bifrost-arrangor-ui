<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\BackendApiClient;

/**
 * Resolver aktiv cup/tenant fra arrangørportalens HTTP-host via backend API.
 */
final class TenantContext
{
    /** @var array<string, mixed>|null */
    private static ?array $cached = null;

    /**
     * @return array{
     *   host: string,
     *   resolved: bool,
     *   error: string|null,
     *   tenant: array<string, mixed>|null,
     *   tenant_id: int,
     *   display_name: string
     * }
     */
    public static function current(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $host = self::requestHost();
        $client = new BackendApiClient();
        $response = $client->resolveTenant($host);

        $tenant = null;
        $error = null;
        if ($response['ok'] && is_array($response['data']['tenant'] ?? null)) {
            $tenant = $response['data']['tenant'];
            $purpose = self::domainPurpose($tenant, $host);
            if ($purpose !== null && $purpose !== 'arrangor') {
                $tenant = null;
                $error = 'Dette domenet er ikke konfigurert som arrangørportal for en cup.';
            }
        } else {
            $error = (string) ($response['error'] ?? 'Kunne ikke finne cup for dette domenet');
        }

        $displayName = is_array($tenant)
            ? trim((string) ($tenant['name'] ?? 'Cup'))
            : 'Cup';

        self::$cached = [
            'host' => $host,
            'resolved' => $tenant !== null,
            'error' => $error,
            'tenant' => $tenant,
            'tenant_id' => is_array($tenant) ? (int) ($tenant['id'] ?? 0) : 0,
            'display_name' => $displayName !== '' ? $displayName : 'Cup',
        ];

        return self::$cached;
    }

    public static function requestHost(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return explode(':', $host)[0];
    }

    /** @param array<string, mixed> $tenant */
    private static function domainPurpose(array $tenant, string $host): ?string
    {
        $domains = is_array($tenant['domains'] ?? null) ? $tenant['domains'] : [];
        foreach ($domains as $domain) {
            if (!is_array($domain)) {
                continue;
            }
            if (strtolower((string) ($domain['host'] ?? '')) === $host) {
                return (string) ($domain['purpose'] ?? '');
            }
        }

        return null;
    }
}
