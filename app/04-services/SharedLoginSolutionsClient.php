<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use App\Support\Database;
use PDO;
use Throwable;

/**
 * Henter listen over løsninger der felles Bifrost-konto kan brukes.
 * Leser primært fra lokal DB (samme som admin-core); faller tilbake til API.
 * Feiler mykt: returnerer tom liste ved feil.
 */
final class SharedLoginSolutionsClient
{
    /**
     * @return list<array{
     *   application_id: int,
     *   name: string,
     *   application_key: string,
     *   primary_hostname: string|null,
     *   url: string|null
     * }>
     */
    public function listSolutions(): array
    {
        try {
            $fromDb = $this->listFromDatabase();
            if ($fromDb !== []) {
                return $fromDb;
            }
        } catch (Throwable) {
            // fortsett til HTTP-fallback
        }

        try {
            return $this->listFromApi();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{
     *   application_id: int,
     *   name: string,
     *   application_key: string,
     *   primary_hostname: string|null,
     *   url: string|null
     * }>
     */
    private function listFromDatabase(): array
    {
        $pdo = Database::pdo();
        if (!$this->hasSharedLoginColumn($pdo)) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT
                a.application_id,
                a.name,
                a.application_key,
                d.hostname AS primary_hostname
            FROM app_applications a
            LEFT JOIN app_domains d
                ON d.application_id = a.application_id
               AND d.deleted_at IS NULL
               AND d.status = 'active'
               AND d.is_primary = 1
               AND d.hostname NOT LIKE 'admin.%'
               AND d.hostname NOT LIKE 'test.%'
               AND d.hostname NOT LIKE 'staging.%'
               AND d.hostname NOT LIKE 'arrangor.%'
            WHERE a.deleted_at IS NULL
              AND a.status = 'active'
              AND a.visibility = 'public'
              AND a.show_in_shared_login_list = 1
            ORDER BY a.name
        ");

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function hasSharedLoginColumn(PDO $pdo): bool
    {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'app_applications'
              AND COLUMN_NAME = 'show_in_shared_login_list'
        ");

        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * @return list<array{
     *   application_id: int,
     *   name: string,
     *   application_key: string,
     *   primary_hostname: string|null,
     *   url: string|null
     * }>
     */
    private function listFromApi(): array
    {
        $baseUrl = rtrim((string) Config::get('events.api_base_url', ''), '/');
        if ($baseUrl === '' || !function_exists('curl_init')) {
            return [];
        }

        $url = $baseUrl . '/api/public/shared-login-solutions';
        $ch = curl_init($url);
        if ($ch === false) {
            return [];
        }

        $headers = ['Accept: application/json'];
        $hostHeader = trim((string) (Config::get('events.api_host_header') ?? ''));
        if ($hostHeader !== '') {
            $headers[] = 'Host: ' . $hostHeader;
        }

        $verifySsl = ($_ENV['EVENTS_SSL_VERIFY'] ?? 'true') !== 'false';
        $curlOptions = [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($responseBody === false || $status < 200 || $status >= 300) {
            return [];
        }

        $decoded = json_decode((string) $responseBody, true);
        if (!is_array($decoded)) {
            return [];
        }

        $solutions = $decoded['data']['solutions'] ?? null;
        if (!is_array($solutions)) {
            return [];
        }

        return $this->normalizeRows($solutions);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{
     *   application_id: int,
     *   name: string,
     *   application_key: string,
     *   primary_hostname: string|null,
     *   url: string|null
     * }>
     */
    private function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $hostname = trim((string) ($item['primary_hostname'] ?? ''));
            $hostname = $hostname !== '' ? $hostname : null;
            $url = isset($item['url']) && is_string($item['url']) && $item['url'] !== ''
                ? $item['url']
                : ($hostname !== null ? $this->publicUrlForHostname($hostname) : null);

            $out[] = [
                'application_id' => (int) ($item['application_id'] ?? 0),
                'name' => $name,
                'application_key' => (string) ($item['application_key'] ?? ''),
                'primary_hostname' => $hostname,
                'url' => $url,
            ];
        }

        return $out;
    }

    private function publicUrlForHostname(string $hostname): string
    {
        $host = strtolower(trim($hostname));
        $scheme = str_ends_with($host, '.local') || str_ends_with($host, '.test') ? 'http' : 'https';

        return $scheme . '://' . $host;
    }
}
