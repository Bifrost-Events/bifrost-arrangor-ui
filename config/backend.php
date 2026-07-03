<?php

require_once __DIR__ . '/require-env.php';

require_env('BACKEND_URL');

return [
    'api_base_url' => rtrim((string) $_ENV['BACKEND_URL'], '/'),
];
