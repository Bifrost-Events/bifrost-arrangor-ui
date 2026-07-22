<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Leser cup-brand (farger/logo) fra same JSON som public-ui — midlertidig SoT
 * til brand ligger på event_spaces / API.
 */
final class PortalCupBrand
{
    /** @var array<string, string> application_key => config-fil */
    private const APP_KEY_MAP = [
        'jaktfeltcup' => 'nasjonal-15m-jaktfeltcup.json',
        'jaktfeltkarusell-namdal' => 'namdal-jaktfeltkarusell.json',
    ];

    /** @var array<string, string> public host (uten port) => config-fil */
    private const HOST_MAP = [
        'jaktfeltcup.local' => 'nasjonal-15m-jaktfeltcup.json',
        'namdal.jaktfeltkarusell.local' => 'namdal-jaktfeltkarusell.json',
        'namdal.local' => 'namdal-jaktfeltkarusell.json',
        'slatlem.local' => 'slatlem-cup.json',
        'slatlemcup.local' => 'slatlem-cup.json',
        'jaktfeltcup.no' => 'nasjonal-15m-jaktfeltcup.json',
        'www.jaktfeltcup.no' => 'nasjonal-15m-jaktfeltcup.json',
        'namdal.jaktfeltkarusell.no' => 'namdal-jaktfeltkarusell.json',
        'www.namdal.jaktfeltkarusell.no' => 'namdal-jaktfeltkarusell.json',
        'slatlemcup.no' => 'slatlem-cup.json',
        'www.slatlemcup.no' => 'slatlem-cup.json',
        'test.jaktfeltcup.no' => 'nasjonal-15m-jaktfeltcup.json',
        'test.namdal.jaktfeltkarusell.no' => 'namdal-jaktfeltkarusell.json',
        'test.slatlemcup.no' => 'slatlem-cup.json',
        'staging.jaktfeltcup.no' => 'nasjonal-15m-jaktfeltcup.json',
        'staging.jaktfeltkarusell.no' => 'namdal-jaktfeltkarusell.json',
        'staging.namdal.jaktfeltkarusell.no' => 'namdal-jaktfeltkarusell.json',
        'staging.slatlemcup.no' => 'slatlem-cup.json',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $cached = null;

    private static ?string $cacheKey = null;

    /**
     * @return array{
     *   resolved: bool,
     *   config_file: string|null,
     *   cup_id: string,
     *   name: string,
     *   primary_color: string,
     *   secondary_color: string,
     *   accent_color: string,
     *   header_bg: string,
     *   logo_path: string,
     *   logo_url: string,
     *   tagline: string,
     *   css_variables: array<string, string>
     * }
     */
    public static function resolve(?string $applicationKey = null, ?string $host = null): array
    {
        $applicationKey = $applicationKey !== null ? trim($applicationKey) : '';
        $host = self::normalizeHost($host ?? self::requestPublicHost());
        $key = $applicationKey . '|' . $host;
        if (self::$cached !== null && self::$cacheKey === $key) {
            return self::$cached;
        }

        $filename = self::resolveFilename($applicationKey, $host);
        $config = $filename !== null ? self::loadConfig($filename) : null;
        if ($config === null) {
            self::$cacheKey = $key;
            self::$cached = self::fallback();

            return self::$cached;
        }

        $brand = is_array($config['brand'] ?? null) ? $config['brand'] : [];
        $primary = self::color($brand['primary_color'] ?? null, '#2c5530');
        $secondary = self::color($brand['secondary_color'] ?? null, $primary);
        $accentSoft = self::color($brand['accent_color'] ?? null, '#e8f0e9');
        $headerBg = self::color($brand['header_bg'] ?? null, $secondary);
        $logoPath = trim((string) ($brand['logo'] ?? ''));
        $configDomain = self::normalizeHost((string) ($config['domain'] ?? ''));
        $publicBase = self::publicSiteBaseUrl($configDomain !== '' ? $configDomain : $host);

        $profile = [
            'resolved' => true,
            'config_file' => $filename,
            'cup_id' => (string) ($config['cup_id'] ?? ''),
            'name' => (string) ($config['name'] ?? ''),
            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'accent_color' => $accentSoft,
            'header_bg' => $headerBg,
            'logo_path' => $logoPath,
            'logo_url' => self::absoluteAssetUrl($logoPath, $publicBase),
            'tagline' => (string) ($brand['tagline'] ?? ''),
            'css_variables' => [
                '--bg' => '#eef0eb',
                '--sidebar' => $headerBg,
                '--accent' => $primary,
                '--accent-hover' => $secondary,
                '--accent-soft' => $accentSoft,
                '--card' => '#fff',
                '--ink' => '#1a1a18',
                '--muted' => '#5c635c',
                '--cup-bar' => $headerBg,
                '--sidebar-border' => $primary,
                '--sidebar-muted' => $accentSoft,
            ],
        ];

        self::$cacheKey = $key;
        self::$cached = $profile;

        return self::$cached;
    }

    /**
     * @return array{
     *   resolved: bool,
     *   config_file: string|null,
     *   cup_id: string,
     *   name: string,
     *   primary_color: string,
     *   secondary_color: string,
     *   accent_color: string,
     *   header_bg: string,
     *   logo_path: string,
     *   logo_url: string,
     *   tagline: string,
     *   css_variables: array<string, string>
     * }
     */
    private static function fallback(): array
    {
        $primary = '#2c5530';
        $secondary = '#1e2a22';

        return [
            'resolved' => false,
            'config_file' => null,
            'cup_id' => '',
            'name' => '',
            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'accent_color' => '#e8f0e9',
            'header_bg' => $secondary,
            'logo_path' => '',
            'logo_url' => '',
            'tagline' => '',
            'css_variables' => [
                '--bg' => '#eef0eb',
                '--sidebar' => $secondary,
                '--accent' => '#3d6b47',
                '--accent-hover' => $primary,
                '--accent-soft' => '#e8f0e9',
                '--card' => '#fff',
                '--ink' => '#1a1a18',
                '--muted' => '#5c635c',
                '--cup-bar' => '#243028',
                '--sidebar-border' => '#7cb087',
                '--sidebar-muted' => '#9aaf9f',
            ],
        ];
    }

    private static function resolveFilename(string $applicationKey, string $host): ?string
    {
        if ($applicationKey !== '' && isset(self::APP_KEY_MAP[$applicationKey])) {
            return self::APP_KEY_MAP[$applicationKey];
        }
        if ($host !== '' && isset(self::HOST_MAP[$host])) {
            return self::HOST_MAP[$host];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private static function loadConfig(string $filename): ?array
    {
        $path = self::configDirectory() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    private static function configDirectory(): string
    {
        $configured = trim((string) ($_ENV['PUBLIC_UI_PATH'] ?? Config::get('app.public_ui_path', '') ?? ''));
        if ($configured !== '') {
            $path = rtrim($configured, '/\\') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cups';
            if (is_dir($path)) {
                return $path;
            }
        }

        // Lokal monorepo: sibling bifrost-public-ui (én SoT under utvikling)
        $sibling = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bifrost-public-ui'
            . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cups';
        if (is_dir($sibling)) {
            return $sibling;
        }

        // Test/prod FTP: bundlet i arrangør-UI (deploy-manifest include config/)
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cups';
    }

    private static function requestPublicHost(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (str_starts_with($host, 'arrangor.')) {
            $host = substr($host, strlen('arrangor.'));
        }

        $baseUrl = trim((string) (Config::get('app.base_url') ?? $_ENV['APP_BASE_URL'] ?? ''));
        if ($host === '' && $baseUrl !== '') {
            $parsed = parse_url($baseUrl);
            $host = strtolower((string) ($parsed['host'] ?? ''));
            if (str_starts_with($host, 'arrangor.')) {
                $host = substr($host, strlen('arrangor.'));
            }
        }

        // namdal: arrangor.jaktfeltnamdalen.local → public host fragår HOST_MAP via application_key oftere
        if ($host === 'jaktfeltnamdalen.local') {
            return 'namdal.jaktfeltkarusell.local';
        }

        return self::normalizeHost($host);
    }

    private static function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (str_starts_with($host, 'arrangor.')) {
            $host = substr($host, strlen('arrangor.'));
        }
        if ($host === 'jaktfeltnamdalen.local') {
            return 'namdal.jaktfeltkarusell.local';
        }

        return $host;
    }

    private static function publicSiteBaseUrl(string $host): string
    {
        $configured = trim((string) ($_ENV['PUBLIC_SITE_URL'] ?? Config::get('app.public_site_url', '') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $baseUrl = trim((string) (Config::get('app.base_url') ?? $_ENV['APP_BASE_URL'] ?? 'http://localhost'));
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http';

        // Cupens egen public-host (fra JSON) trumfer PUBLIC_REGISTER_URL (kan være annen cup).
        if ($host !== '') {
            return $scheme . '://' . $host;
        }

        $register = trim((string) (Config::get('app.public_register_url') ?? $_ENV['PUBLIC_REGISTER_URL'] ?? ''));
        if ($register !== '') {
            $parts = parse_url($register);
            if (is_array($parts) && ($parts['scheme'] ?? '') !== '' && ($parts['host'] ?? '') !== '') {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';

                return $parts['scheme'] . '://' . $parts['host'] . $port;
            }
        }

        return '';
    }

    private static function absoluteAssetUrl(string $path, string $publicBase): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if ($publicBase === '') {
            return $path;
        }

        return rtrim($publicBase, '/') . '/' . ltrim($path, '/');
    }

    private static function color(mixed $value, string $fallback): string
    {
        $color = trim((string) $value);
        if ($color === '' || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $fallback;
        }

        return $color;
    }
}
