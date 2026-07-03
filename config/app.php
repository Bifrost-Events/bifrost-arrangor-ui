<?php

require_once __DIR__ . '/require-env.php';

require_env('APP_ENV');
require_env('APP_DEBUG');
require_env('APP_BASE_URL');
require_env('PUBLIC_REGISTER_URL');
require_env('ARRANGOR_PORTAL_URL');

return [
    'name' => $_ENV['APP_NAME'] ?? 'Bifrost Arrangør',
    'env' => $_ENV['APP_ENV'],
    'base_url' => rtrim((string) $_ENV['APP_BASE_URL'], '/'),
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'public_register_url' => rtrim((string) $_ENV['PUBLIC_REGISTER_URL'], '/'),
    'arrangor_portal_url' => rtrim((string) $_ENV['ARRANGOR_PORTAL_URL'], '/'),
];
