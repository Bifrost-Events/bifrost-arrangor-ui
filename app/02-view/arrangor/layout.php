<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $content */
/** @var string $active_nav */
/** @var array<string, mixed>|null $user */
/** @var list<array<string, mixed>> $menu_sections */
/** @var array<string, mixed>|null $menu_overview */
/** @var array<string, mixed> $organizer_context */
/** @var string $public_register_url */

$activeNav = $active_nav ?? '';
$user = $user ?? null;
$menuSections = $menu_sections ?? [];
$menuOverview = $menu_overview ?? null;
$organizerContext = $organizer_context ?? [
    'selected_organization_id' => null,
    'selected_organization' => null,
    'selected_season_id' => null,
    'selected_season' => null,
    'selectable_organizations' => [],
    'approval' => null,
    'can_write' => false,
];

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$userName = '';
$userEmail = '';
if (is_array($user)) {
    $userName = trim((string) ($user['name'] ?? ''));
    $userEmail = (string) ($user['email'] ?? '');
    if ($userName === '') {
        $userName = $userEmail;
    }
}

$selectedOrg = is_array($organizerContext['selected_organization'] ?? null)
    ? $organizerContext['selected_organization']
    : null;
$selectableOrgs = is_array($organizerContext['selectable_organizations'] ?? null)
    ? $organizerContext['selectable_organizations']
    : [];
$selectedSeason = is_array($organizerContext['selected_season'] ?? null)
    ? $organizerContext['selected_season']
    : null;
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

$activeSectionId = '';
foreach ($menuSections as $section) {
    if (!is_array($section)) {
        continue;
    }
    foreach ($section['items'] ?? [] as $item) {
        if (is_array($item) && ($item['id'] ?? '') === $activeNav) {
            $activeSectionId = (string) ($section['id'] ?? '');
            break 2;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title) ?> – Bifrost Arrangør</title>
    <style>
        :root {
            --bg: #eef0eb;
            --sidebar: #1e2a22;
            --sidebar-text: #e8ece9;
            --sidebar-muted: #9caaa3;
            --sidebar-active: #3d6b47;
            --topbar: #fff;
            --card: #fff;
            --ink: #1a1a18;
            --muted: #5c5c58;
            --line: #d4d8d2;
            --accent: #2c5530;
            --bad: #9b2c2c;
            --ok: #2c5530;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--ink); line-height: 1.45; }
        a { color: var(--accent); }
        .admin-shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px; flex-shrink: 0; background: var(--sidebar); color: var(--sidebar-text);
            display: flex; flex-direction: column; padding: 1rem 0;
        }
        .sidebar-brand { padding: 0 1rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 0.75rem; }
        .sidebar-brand strong { display: block; font-size: 1rem; }
        .sidebar-brand span { font-size: 0.8rem; color: var(--sidebar-muted); }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 0 0.5rem; }
        .nav-overview { margin-bottom: 0.5rem; }
        .nav-overview a, .nav-item a {
            display: block; padding: 0.45rem 0.75rem; border-radius: 4px;
            color: var(--sidebar-text); text-decoration: none; font-size: 0.92rem;
        }
        .nav-overview a:hover, .nav-item a:hover { background: rgba(255,255,255,0.06); }
        .nav-overview a.is-active, .nav-item a.is-active {
            background: var(--sidebar-active); color: #fff; font-weight: 600;
        }
        .nav-section { margin-top: 0.35rem; }
        .nav-section-title {
            padding: 0.45rem 0.75rem; color: var(--sidebar-muted); font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
        }
        .main-area { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar {
            background: var(--topbar); border-bottom: 1px solid var(--line);
            padding: 0.65rem 1.25rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1.25rem;
        }
        .topbar-title { font-weight: 700; font-size: 1.05rem; margin: 0; }
        .context-picker { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
        .context-picker label { color: var(--muted); font-weight: 600; }
        .context-picker select {
            min-width: 200px; padding: 0.35rem 0.5rem; border: 1px solid var(--line);
            border-radius: 4px; font-size: 0.9rem; background: #fff;
        }
        .topbar-user { margin-left: auto; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: var(--muted); }
        .topbar-user .name { color: var(--ink); font-weight: 600; }
        .cup-season-context {
            display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; align-items: center;
            padding: 0.45rem 0.85rem; background: #f4f7f4; border: 1px solid #d8e3da;
            border-radius: 6px; font-size: 0.9rem;
        }
        .cup-season-context__item { display: flex; align-items: baseline; gap: 0.4rem; }
        .cup-season-context__label {
            color: var(--muted); font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .btn-logout {
            background: transparent; border: 1px solid var(--line); border-radius: 4px;
            padding: 0.3rem 0.65rem; font-size: 0.85rem; cursor: pointer; color: var(--ink);
        }
        .content-wrap { padding: 1.25rem; flex: 1; }
        .content-card { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 1.25rem 1.5rem; }
        .lead { color: var(--muted); margin-top: 0; }
        .placeholder-box {
            margin-top: 1.25rem; padding: 1.25rem; border: 1px dashed var(--line);
            border-radius: 6px; background: #fafbf9;
        }
        .badge { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .badge-ok { background: #e6f2e8; color: var(--ok); }
        .badge-bad { background: #fdeaea; color: var(--bad); }
        .badge-pending { background: #fff4e5; color: #8a5a00; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.55rem 0.65rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 0.85rem; color: var(--muted); }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 1rem 0; }
        .btn {
            display: inline-block; padding: 0.4rem 0.85rem; border-radius: 4px; border: 1px solid var(--line);
            background: #fff; color: var(--ink); text-decoration: none; font-size: 0.9rem; cursor: pointer;
        }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .form-grid { display: grid; gap: 0.85rem; max-width: 520px; margin-top: 1rem; }
        .form-grid label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .form-grid input, .form-grid select, .form-grid textarea {
            width: 100%; padding: 0.45rem 0.55rem; border: 1px solid var(--line); border-radius: 4px; font-size: 0.95rem;
        }
        .form-error { color: var(--bad); margin: 0.75rem 0; }
        .flash { padding: 0.65rem 0.85rem; border-radius: 4px; margin-bottom: 1rem; font-weight: 600; }
        .flash-success { background: #e6f2e8; color: var(--ok); }
        .flash-error { background: #fdeaea; color: var(--bad); }
        .flash-info { background: #e8f0f8; color: #1a4a6e; }
        @media (max-width: 900px) {
            .admin-shell { flex-direction: column; }
            .sidebar { width: 100%; max-height: 40vh; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" aria-label="Hovedmeny">
        <div class="sidebar-brand">
            <strong>Bifrost Arrangør</strong>
            <span>Stevner og deltakere</span>
        </div>
        <nav class="sidebar-nav">
            <?php if (is_array($menuOverview)): ?>
                <div class="nav-overview">
                    <a href="<?= $h((string) ($menuOverview['path'] ?? '/')) ?>"
                       class="<?= $activeNav === ($menuOverview['id'] ?? '') ? 'is-active' : '' ?>">
                        <?= $h((string) ($menuOverview['label'] ?? 'Oversikt')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php foreach ($menuSections as $section): ?>
                <?php if (!is_array($section)) {
                    continue;
                } ?>
                <div class="nav-section">
                    <div class="nav-section-title"><?= $h((string) ($section['label'] ?? '')) ?></div>
                    <?php foreach ($section['items'] ?? [] as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <div class="nav-item">
                            <a href="<?= $h((string) ($item['path'] ?? '#')) ?>"
                               class="<?= $activeNav === ($item['id'] ?? '') ? 'is-active' : '' ?>">
                                <?= $h((string) ($item['label'] ?? '')) ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="nav-item" style="margin-top:1rem;">
                <a href="/bli-arrangor">Bli arrangør</a>
            </div>
        </nav>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <p class="topbar-title">Bifrost Arrangør</p>

            <?php if ($selectableOrgs !== []): ?>
                <form class="context-picker" method="get" action="<?= $h($currentPath) ?>">
                    <label for="organization_id">Arrangør</label>
                    <select id="organization_id" name="organization_id" onchange="this.form.submit()">
                        <?php foreach ($selectableOrgs as $org): ?>
                            <?php if (!is_array($org)) {
                                continue;
                            } ?>
                            <?php $oid = (int) ($org['id'] ?? 0); ?>
                            <option value="<?= $oid ?>"<?= $selectedOrg !== null && (int) ($selectedOrg['id'] ?? 0) === $oid ? ' selected' : '' ?>>
                                <?= $h((string) ($org['name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <?php
            $organizer_context = $organizerContext;
            include __DIR__ . '/_cup-season-context.php';
            ?>

            <?php if ($userEmail !== ''): ?>
                <div class="topbar-user">
                    <span class="name"><?= $h($userName) ?></span>
                    <form method="post" action="/logout" style="margin:0;">
                        <button type="submit" class="btn-logout">Logg ut</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <div class="content-wrap">
            <main class="content-card">
                <?php if (is_array($flash ?? null) && (($flash['message'] ?? '') !== '')): ?>
                    <div class="flash flash-<?= $h((string) $flash['type']) ?>">
                        <?= $h((string) $flash['message']) ?>
                    </div>
                <?php endif; ?>
                <?= $content ?>
            </main>
        </div>
    </div>
</div>
</body>
</html>
