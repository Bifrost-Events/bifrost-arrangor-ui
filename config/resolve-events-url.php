<?php

declare(strict_types=1);

/**
 * Base-URL for bifrost-events organizer API.
 * Eksplisitt EVENTS_URL overstyrer; ellers utledes fra BACKEND_URL.
 *
 * @return array{base_url: string, host_header: string|null, public_url: string}
 */
function resolve_events_api_transport(): array
{
    $publicUrl = resolve_events_api_base_url();
    $parsed = is_string($publicUrl) ? parse_url($publicUrl) : false;
    $host = is_array($parsed) ? strtolower((string) ($parsed['host'] ?? '')) : '';

    $useLoopback = ($_ENV['APP_ENV'] ?? 'production') === 'development'
        && ($_ENV['EVENTS_USE_LOOPBACK'] ?? 'true') !== 'false'
        && $host !== ''
        && !filter_var($host, FILTER_VALIDATE_IP);

    if (!$useLoopback) {
        return [
            'base_url' => $publicUrl,
            'host_header' => null,
            'public_url' => $publicUrl,
        ];
    }

    $scheme = is_array($parsed) ? ($parsed['scheme'] ?? 'http') : 'http';
    $port = is_array($parsed) && isset($parsed['port']) ? ':' . (int) $parsed['port'] : '';

    return [
        'base_url' => $scheme . '://127.0.0.1' . $port,
        'host_header' => $host,
        'public_url' => $publicUrl,
    ];
}

/**
 * Base-URL for bifrost-events organizer API (offentlig/visnings-URL).
 */
function resolve_events_api_base_url(): string
{
    $explicit = trim((string) ($_ENV['EVENTS_URL'] ?? ''));
    if ($explicit !== '') {
        return rtrim($explicit, '/');
    }

    $adminUrl = trim((string) ($_ENV['ADMIN_URL'] ?? ''));
    if ($adminUrl !== '') {
        return rtrim($adminUrl, '/');
    }

    $backend = rtrim((string) ($_ENV['BACKEND_URL'] ?? ''), '/');
    if ($backend !== '') {
        return $backend;
    }

    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        return 'http://admin.bifrost.local';
    }

    return '';
}

/**
 * Absolutt sti til bifrost-admin-core (for intern API-dispatch i dev).
 */
function resolve_admin_core_path(): string
{
    $fromEnv = trim((string) ($_ENV['ADMIN_CORE_PATH'] ?? ''));
    if ($fromEnv !== '') {
        $resolved = realpath($fromEnv);

        return $resolved !== false ? $resolved : $fromEnv;
    }

    $default = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bifrost-admin-core';

    return realpath($default) ?: $default;
}
