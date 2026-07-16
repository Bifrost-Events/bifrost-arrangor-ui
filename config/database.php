<?php

declare(strict_types=1);

return [
    'dsn' => $_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;dbname=bifrost_admin_core;charset=utf8mb4',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'pass' => $_ENV['DB_PASS'] ?? '',
];
