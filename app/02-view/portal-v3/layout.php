<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $content */
/** @var array<string, mixed>|null $user */
/** @var array<string, mixed>|null $flash */
/** @var array<string, mixed>|null $active_org */
/** @var list<array<string, mixed>> $organizations */
/** @var int|null $active_space_id */
/** @var array<string, mixed>|null $active_space */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var string $route_prefix */
/** @var class-string<\App\Support\PortalPaths> $pp */
/** @var list<array{label: string, href: string, active: bool}> $menu */
/** @var bool $domain_bound */
/** @var array<string, mixed> $cup_brand */
/** @var string $season_label */
/** @var list<array{series_id: int, label: string}> $season_options */
/** @var int|null $season_series_id */
/** @var int $cup_owner_org_id */
/** @var array<string, mixed> $work_context */

$pp = $pp ?? \App\Support\PortalPaths::class;
$menu = is_array($menu ?? null) ? $menu : [];
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isLoggedIn = is_array($user) && $user !== [];
$userName = $isLoggedIn ? (string) ($user['name'] ?? $user['email'] ?? '') : '';
$userEmail = $isLoggedIn ? (string) ($user['email'] ?? '') : '';
$orgName = is_array($active_org) ? (string) ($active_org['name'] ?? '') : '';
$activeOrgId = is_array($active_org) ? (int) ($active_org['org_id'] ?? 0) : 0;
$orgList = is_array($organizations ?? null) ? $organizations : [];
$cupName = is_array($active_space) ? (string) ($active_space['name'] ?? '') : '';
$cupId = is_array($active_space) ? (int) ($active_space['space_id'] ?? 0) : 0;
$cupLabel = $labels->singular('event_space');
$hasCup = $cupName !== '' && $cupId > 0;
$domainBound = (bool) ($domain_bound ?? false);
$seasonLabel = trim((string) ($season_label ?? ''));
$seasonOptions = is_array($season_options ?? null) ? $season_options : [];
$seasonSeriesId = (int) ($season_series_id ?? 0);
$cupOwnerOrgId = (int) ($cup_owner_org_id ?? ($active_space['owner_org_id'] ?? 0));
$brand = is_array($cup_brand ?? null) ? $cup_brand : [];
$cssVars = is_array($brand['css_variables'] ?? null) ? $brand['css_variables'] : [];
$logoUrl = trim((string) ($brand['logo_url'] ?? ''));
$pageTitle = $hasCup
    ? ($cupName . ($seasonLabel !== '' ? ' · ' . $seasonLabel : '') . ' – ' . $title)
    : $title;
$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$pp = $pp ?? \App\Support\PortalPaths::class;
$orgReturn = $currentPath !== '' ? $currentPath . ($query !== '' ? '?' . $query : '') : $pp::cups();
$seasonReturn = $orgReturn;

$rootCss = '';
foreach ($cssVars as $name => $value) {
    $rootCss .= '            ' . $name . ': ' . $value . ";\n";
}
?>
<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($pageTitle) ?> – Bifrost Arrangør</title>
    <style>
        :root {
<?= $rootCss !== '' ? $rootCss : "            --bg: #eef0eb;\n            --sidebar: #1e2a22;\n            --accent: #3d6b47;\n            --card: #fff;\n            --ink: #1a1a18;\n            --muted: #5c635c;\n            --cup-bar: #243028;\n            --sidebar-border: #7cb087;\n            --sidebar-muted: #9aaf9f;\n" ?>
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        .shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .sidebar { background: var(--sidebar); color: #e8ece9; padding: 1.25rem; }
        .sidebar a { color: #e8ece9; text-decoration: none; display: block; padding: .4rem 0; }
        .sidebar a:hover { color: #fff; }
        .badge { font-size: .75rem; background: rgba(0,0,0,.25); padding: .15rem .5rem; border-radius: 4px; }
        .brand-logo {
            display: block;
            max-width: 160px;
            max-height: 56px;
            width: auto;
            height: auto;
            object-fit: contain;
            margin: 0 0 .85rem;
        }
        .sidebar-cup, .sidebar-orgs {
            margin: .85rem 0 1rem;
            padding: .65rem .75rem;
            background: rgba(0,0,0,.22);
            border-left: 3px solid var(--sidebar-border, var(--accent));
            border-radius: 4px;
        }
        .sidebar-cup .eyebrow, .sidebar-orgs .eyebrow {
            display: block;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--sidebar-muted, #9aaf9f);
            margin-bottom: .35rem;
        }
        .sidebar-cup a {
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.25;
            color: #fff;
            padding: 0;
        }
        .sidebar-orgs a {
            padding: .25rem 0;
            font-size: .9rem;
            line-height: 1.3;
        }
        .sidebar-orgs a.is-active {
            font-weight: 700;
            color: #fff;
        }
        .sidebar-orgs .org-all {
            margin-top: .35rem;
            font-size: .8rem;
            color: var(--sidebar-muted, #9aaf9f);
        }
        .sidebar-work {
            margin: .85rem 0 1rem;
            padding: .65rem .75rem;
            background: rgba(0,0,0,.18);
            border-radius: 4px;
            border-left: 3px solid var(--sidebar-border, var(--accent));
        }
        .sidebar-work > .eyebrow {
            display: block;
            margin: 0 0 .55rem;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--sidebar-muted, #9aaf9f);
            font-weight: 600;
        }
        .sidebar-work .work-section {
            margin-top: .65rem;
        }
        .sidebar-work .work-section:first-of-type { margin-top: 0; }
        .sidebar-work .work-section-title {
            display: block;
            margin: 0 0 .25rem;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--sidebar-muted, #9aaf9f);
            font-weight: 600;
        }
        .sidebar-work .work-link {
            display: block;
            padding: .3rem 0;
            font-size: .9rem;
            line-height: 1.3;
            color: #e8ece9;
            text-decoration: none;
        }
        .sidebar-work .work-link:hover { color: #fff; }
        .sidebar-work .work-link.is-active {
            color: #fff;
            font-weight: 700;
        }
        .sidebar-work .work-link .work-meta {
            display: block;
            margin-top: .1rem;
            font-size: .75rem;
            font-weight: 500;
            color: var(--sidebar-muted, #9aaf9f);
        }
        .sidebar-work .work-empty {
            display: block;
            font-size: .8rem;
            color: var(--sidebar-muted, #9aaf9f);
            padding: .15rem 0;
        }
        main { padding: 0; }
        .main-inner { padding: 1.5rem 2rem; }
        .workspace-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: .15rem 1rem;
            padding: .65rem 2rem 0;
            border-bottom: 1px solid rgba(0,0,0,.08);
            background: var(--card, #fff);
        }
        .workspace-tabs a {
            display: inline-block;
            padding: .55rem .1rem .7rem;
            color: var(--muted, #5c635c);
            text-decoration: none;
            font-size: .95rem;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .workspace-tabs a:hover { color: var(--ink, #1a1a18); }
        .workspace-tabs a.is-active {
            color: var(--accent, #3d6b47);
            border-bottom-color: var(--accent, #3d6b47);
        }
        .cup-context {
            background: var(--cup-bar);
            color: #eef3ef;
            padding: .85rem 2rem;
            border-bottom: 1px solid rgba(0,0,0,.2);
            display: flex;
            align-items: center;
            gap: .75rem 1.25rem;
            flex-wrap: wrap;
        }
        .cup-context .cup-logo {
            max-height: 40px;
            max-width: 120px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 2px;
        }
        .cup-context .eyebrow {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--sidebar-muted, #9bbb9f);
            font-weight: 600;
        }
        .cup-context .cup-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }
        .cup-context .cup-name:hover { text-decoration: underline; }
        .cup-context .cup-switch {
            margin-left: auto;
            font-size: .85rem;
            color: rgba(255,255,255,.75);
            text-decoration: none;
        }
        .cup-context .cup-switch:hover { color: #fff; }
        .season-switch {
            margin-left: auto;
            display: flex;
            flex-direction: column;
            gap: .25rem;
            min-width: 14rem;
            max-width: 28rem;
        }
        .season-switch label {
            margin: 0;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--sidebar-muted, #9bbb9f);
            font-weight: 600;
        }
        .season-switch select {
            width: 100%;
            max-width: none;
            padding: .4rem .5rem;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,.25);
            background: rgba(0,0,0,.25);
            color: #fff;
            font-size: .9rem;
        }
        .season-switch .season-current {
            font-size: .95rem;
            font-weight: 600;
            color: #fff;
        }
        .account-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem 1rem;
            flex-wrap: wrap;
            padding: .55rem 2rem;
            background: #f7f8f6;
            border-bottom: 1px solid #e2e5df;
            font-size: .9rem;
        }
        .account-bar .account-user {
            color: var(--ink);
            font-weight: 600;
        }
        .account-bar .account-email {
            color: var(--muted);
            font-weight: 400;
            font-size: .85rem;
        }
        .account-bar a.account-login {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .account-bar a.account-login:hover { text-decoration: underline; }
        .account-bar form { margin: 0; }
        .account-bar .btn {
            padding: .35rem .7rem;
            font-size: .85rem;
        }
        .card { background: var(--card); border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 1rem; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash.error { background: #fde8e8; color: #8b1a1a; }
        .flash.success { background: #e6f4ea; color: #1e5c33; }
        .btn { display: inline-block; background: var(--accent); color: #fff; padding: .5rem 1rem; border-radius: 6px; text-decoration: none; border: 0; cursor: pointer; }
        .btn.secondary { background: #6b7280; }
        .btn.danger { background: #9b2c2c; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7e2; }
        label { display: block; margin: .5rem 0 .25rem; font-weight: 600; }
        input, select, textarea { width: 100%; max-width: 32rem; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
        .muted { color: var(--muted); font-size: .9rem; }
        .nav-active { font-weight: 700; color: #fff !important; }
        @media (max-width: 800px) {
            .shell { grid-template-columns: 1fr; }
            .cup-context { padding: .75rem 1rem; }
            .account-bar { padding: .55rem 1rem; }
            .main-inner { padding: 1rem; }
            .workspace-tabs { padding: .5rem 1rem 0; }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <?php if ($logoUrl !== ''): ?>
            <img class="brand-logo" src="<?= $h($logoUrl) ?>" alt="<?= $h($cupName !== '' ? $cupName : 'Cup-logo') ?>">
        <?php endif; ?>
        <p><span class="badge">Arrangørportal</span></p>

        <?php
        $work = is_array($work_context ?? null) ? $work_context : [];
        $workOptions = is_array($work['options'] ?? null) ? $work['options'] : [];
        $workKey = (string) ($work['key'] ?? '');
        $cupOpts = [];
        $arrangerOpts = [];
        foreach ($workOptions as $opt) {
            if (($opt['mode'] ?? '') === 'cup') {
                $cupOpts[] = $opt;
            } elseif (($opt['mode'] ?? '') === 'arranger') {
                $arrangerOpts[] = $opt;
            }
        }
                        $workSwitchBase = $pp::kontekstArbeidsomrade() . '?work_key=';
        ?>
        <?php if ($hasCup && ($cupOpts !== [] || $arrangerOpts !== [])): ?>
            <div class="sidebar-work" aria-label="Arbeidsområde">
                <span class="eyebrow">Arbeidsområde</span>

                <?php if ($cupOpts !== []): ?>
                    <div class="work-section">
                        <span class="work-section-title">Cupadministrasjon</span>
                        <?php foreach ($cupOpts as $opt): ?>
                            <?php
                            $okey = (string) ($opt['key'] ?? '');
                            $odetail = trim((string) ($opt['detail'] ?? $cupName));
                            $href = $workSwitchBase . rawurlencode($okey);
                            $active = $okey === $workKey;
                            ?>
                            <a class="work-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= $h($href) ?>"
                               <?= $active ? 'aria-current="true"' : '' ?>>
                                <?= $h($odetail !== '' ? $odetail : 'Cup') ?>
                                <?php if ($active): ?>
                                    <span class="work-meta">Aktiv</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($arrangerOpts !== []): ?>
                    <div class="work-section">
                        <span class="work-section-title">Stevnearrangør</span>
                        <?php foreach ($arrangerOpts as $opt): ?>
                            <?php
                            $okey = (string) ($opt['key'] ?? '');
                            $odetail = trim((string) ($opt['detail'] ?? ''));
                            $href = $workSwitchBase . rawurlencode($okey);
                            $active = $okey === $workKey;
                            ?>
                            <a class="work-link<?= $active ? ' is-active' : '' ?>"
                               href="<?= $h($href) ?>"
                               <?= $active ? 'aria-current="true"' : '' ?>>
                                <?= $h($odetail !== '' ? $odetail : 'Arrangør') ?>
                                <?php if ($active): ?>
                                    <span class="work-meta">Aktiv</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($cupOpts !== []): ?>
                    <div class="work-section">
                        <span class="work-section-title">Stevnearrangør</span>
                        <span class="work-empty">Ingen arrangørklubber du administrerer i denne cupen.</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($hasCup): ?>
            <div class="sidebar-work">
                <span class="eyebrow"><?= $h($cupLabel) ?></span>
                <a class="work-link is-active" href="<?= $h($pp::oversikt()) ?>"><?= $h($cupName) ?></a>
            </div>
        <?php elseif ($orgName !== ''): ?>
            <p class="muted">Organisasjon: <?= $h($orgName) ?></p>
        <?php endif; ?>
    </aside>
    <main>
        <header class="account-bar" aria-label="Konto">
            <?php if ($isLoggedIn): ?>
                <span class="account-user">
                    <?= $h($userName !== '' ? $userName : 'Innlogget') ?>
                    <?php if ($userEmail !== '' && $userEmail !== $userName): ?>
                        <span class="account-email"><?= $h($userEmail) ?></span>
                    <?php endif; ?>
                </span>
                <form method="post" action="<?= $h($pp::logout()) ?>">
                    <button type="submit" class="btn secondary">Logg ut</button>
                </form>
            <?php else: ?>
                <a class="account-login" href="<?= $h($pp::login()) ?>">Logg inn</a>
            <?php endif; ?>
        </header>
        <?php if ($hasCup): ?>
            <header class="cup-context" aria-label="Aktiv <?= $h($cupLabel) ?>">
                <?php if ($logoUrl !== ''): ?>
                    <img class="cup-logo" src="<?= $h($logoUrl) ?>" alt="">
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; gap:.15rem; min-width:0;">
                    <span class="eyebrow"><?= $h($cupLabel) ?></span>
                    <a class="cup-name" href="<?= $h($pp::oversikt()) ?>"><?= $h($cupName) ?></a>
                </div>
                <div class="season-switch" aria-label="Valgt sesong">
                    <label for="season_series_id">Valgt sesong</label>
                    <?php if (count($seasonOptions) > 1): ?>
                        <form method="post" action="<?= $h($pp::kontekstSesong()) ?>" style="margin:0;">
                            <input type="hidden" name="return" value="<?= $h($seasonReturn) ?>">
                            <select id="season_series_id" name="season_series_id" onchange="this.form.submit()">
                                <?php foreach ($seasonOptions as $opt): ?>
                                    <?php $sid = (int) ($opt['series_id'] ?? 0); ?>
                                    <option value="<?= $sid ?>" <?= $sid === $seasonSeriesId ? 'selected' : '' ?>>
                                        <?= $h((string) ($opt['label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php elseif ($seasonLabel !== ''): ?>
                        <span class="season-current" id="season_series_id"><?= $h($seasonLabel) ?></span>
                    <?php else: ?>
                        <span class="season-current">Ingen sesong</span>
                    <?php endif; ?>
                </div>
                <?php if (!$domainBound): ?>
                    <a class="cup-switch" href="<?= $h($pp::cups()) ?>">Bytt <?= $h(strtolower($cupLabel)) ?></a>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <?php if ($menu !== []): ?>
            <nav class="workspace-tabs" aria-label="Arbeidsområde">
                <?php foreach ($menu as $item): ?>
                    <a href="<?= $h($item['href']) ?>"
                       class="<?= ($item['active'] ?? false) ? 'is-active' : '' ?>"
                       <?= ($item['active'] ?? false) ? 'aria-current="page"' : '' ?>>
                        <?= $h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <div class="main-inner">
            <?php if (is_array($flash) && ($flash['message'] ?? '') !== ''): ?>
                <div class="flash <?= $h((string) ($flash['type'] ?? 'info')) ?>">
                    <?= $h((string) $flash['message']) ?>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </main>
</div>
</body>
</html>
