<?php

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=bifrost_admin_core;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

foreach ([
    'person_people',
    'auth_users',
    'org_organizations',
    'org_memberships',
    'org_membership_roles',
    'event_spaces',
    'event_series',
    'event_events',
    'app_applications',
] as $table) {
    try {
        $count = $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        echo $table . ': ' . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $table . ': MISSING' . PHP_EOL;
    }
}

echo '--- demo orgs ---' . PHP_EOL;
$stmt = $pdo->query("SELECT org_id, name, legacy_id FROM org_organizations WHERE legacy_source = 'events_seed'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo '--- users ---' . PHP_EOL;
$stmt = $pdo->query('SELECT u.user_id, u.email, p.display_name FROM auth_users u JOIN person_people p ON p.person_id = u.person_id LIMIT 10');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo '--- memberships with roles ---' . PHP_EOL;
$stmt = $pdo->query("
    SELECT m.membership_id, m.org_id, o.name AS org_name, p.display_name, r.role_key
    FROM org_memberships m
    JOIN org_organizations o ON o.org_id = m.org_id
    JOIN person_people p ON p.person_id = m.person_id
    LEFT JOIN org_membership_roles omr ON omr.membership_id = m.membership_id AND omr.status = 'active'
    LEFT JOIN auth_roles r ON r.role_id = omr.role_id
    LIMIT 20
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
