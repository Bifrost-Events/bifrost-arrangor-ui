<?php

declare(strict_types=1);

require_once __DIR__ . '/resolve-events-url.php';

$transport = resolve_events_api_transport();

return [
    'api_base_url' => $transport['base_url'],
    'api_host_header' => $transport['host_header'],
    'api_public_url' => $transport['public_url'],
    'use_internal_dispatch' => ($_ENV['APP_ENV'] ?? 'production') === 'development'
        && ($_ENV['EVENTS_USE_INTERNAL_DISPATCH'] ?? 'true') !== 'false',
    'admin_core_path' => resolve_admin_core_path(),
];
