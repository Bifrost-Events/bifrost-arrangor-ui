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
/** @var list<array{label: string, href: string}> $account_links */
/** @var bool $domain_bound */
/** @var array<string, mixed> $cup_brand */
/** @var string $season_label */
/** @var list<array{series_id: int, label: string}> $season_options */
/** @var int|null $season_series_id */
/** @var int $cup_owner_org_id */
/** @var array<string, mixed> $work_context */

$pp = $pp ?? \App\Support\PortalPaths::class;
$menu = is_array($menu ?? null) ? $menu : [];
$accountLinks = is_array($account_links ?? null) ? $account_links : [];
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
$orgReturn = $currentPath !== '' ? $currentPath . ($query !== '' ? '?' . $query : '') : $pp::cups();
$seasonReturn = $orgReturn;

$work = is_array($work_context ?? null) ? $work_context : [];
$workOptions = is_array($work['options'] ?? null) ? $work['options'] : [];
$workKey = (string) ($work['key'] ?? '');
$workMode = (string) ($work['mode'] ?? '');
$workLabel = (string) ($work['label'] ?? '');
$workDetail = trim((string) ($work['detail'] ?? ''));
$workSwitchBase = $pp::kontekstArbeidsomrade() . '?work_key=';
$canSwitchWork = count($workOptions) > 1;
$isArrangerMode = $workMode === \App\Support\PortalV3Session::WORK_MODE_ARRANGER;
$modeSummary = $workLabel !== ''
    ? ($workLabel . ($workDetail !== '' ? ' · ' . $workDetail : ''))
    : '';

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
        .shell { min-height: 100vh; display: flex; flex-direction: column; }
        main { flex: 1; padding: 0; }
        /* Konservativ innholdsbredde — midtstilt på brede skjermer */
        :root {
            --content-max: 56rem;
            --content-gutter: 1.25rem;
        }
        .main-inner {
            max-width: var(--content-max);
            margin: 0 auto;
            padding: 1.25rem var(--content-gutter);
            width: 100%;
        }
        .account-bar,
        .cup-context,
        .workspace-tabs {
            padding-left: max(var(--content-gutter), calc((100% - var(--content-max)) / 2 + var(--content-gutter)));
            padding-right: max(var(--content-gutter), calc((100% - var(--content-max)) / 2 + var(--content-gutter)));
        }
        .workspace-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: .15rem 1rem;
            padding-top: .65rem;
            padding-bottom: 0;
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
            padding-top: .85rem;
            padding-bottom: .85rem;
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
            gap: .75rem 1.25rem;
            flex-wrap: wrap;
            padding-top: .55rem;
            padding-bottom: .55rem;
            background: #f7f8f6;
            border-bottom: 1px solid #e2e5df;
            font-size: .9rem;
        }
        .account-bar .mode-switch {
            margin-right: auto;
            position: relative;
        }
        .account-bar .mode-switch summary {
            list-style: none;
            cursor: pointer;
            display: inline-flex;
            flex-direction: column;
            gap: .1rem;
            padding: .25rem .55rem;
            border-radius: 6px;
            border: 1px solid #d5dad3;
            background: #fff;
            min-width: 12rem;
        }
        .account-bar .mode-switch summary::-webkit-details-marker { display: none; }
        .account-bar .mode-switch summary:hover { border-color: var(--accent); }
        .account-bar .mode-eyebrow {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            font-weight: 600;
        }
        .account-bar .mode-current {
            font-weight: 700;
            color: var(--ink);
            font-size: .9rem;
        }
        .account-bar .mode-menu {
            position: absolute;
            left: 0;
            top: calc(100% + .35rem);
            z-index: 40;
            min-width: 16rem;
            background: #fff;
            border: 1px solid #d5dad3;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            padding: .35rem;
        }
        .account-bar .mode-menu a {
            display: block;
            padding: .55rem .65rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--ink);
        }
        .account-bar .mode-menu a:hover { background: #eef2ee; }
        .account-bar .mode-menu a.is-active {
            background: #e6f0e8;
            font-weight: 700;
            color: var(--accent);
        }
        .account-bar .mode-menu .mode-opt-label {
            display: block;
            font-size: .8rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .1rem;
        }
        .account-bar .mode-menu .mode-opt-detail {
            font-size: .9rem;
            font-weight: 600;
        }
        .account-bar .mode-static {
            display: inline-flex;
            flex-direction: column;
            gap: .1rem;
            padding: .25rem .1rem;
        }
        .account-menu {
            position: relative;
        }
        .account-menu summary {
            list-style: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            font-weight: 600;
            color: var(--ink);
            padding: .25rem .1rem;
        }
        .account-menu summary::-webkit-details-marker { display: none; }
        .account-menu summary:hover { color: var(--accent); }
        .account-menu .account-email {
            color: var(--muted);
            font-weight: 400;
            font-size: .85rem;
        }
        .account-menu .account-chevron {
            font-size: .7rem;
            color: var(--muted);
        }
        .account-menu .account-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + .35rem);
            z-index: 40;
            min-width: 14rem;
            background: #fff;
            border: 1px solid #d5dad3;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            padding: .35rem;
        }
        .account-menu .account-dropdown a {
            display: block;
            padding: .5rem .65rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--ink);
            font-weight: 500;
        }
        .account-menu .account-dropdown a:hover { background: #eef2ee; }
        .account-menu .account-dropdown form {
            margin: .25rem 0 0;
            padding: .35rem .35rem .15rem;
            border-top: 1px solid #e8ebe5;
        }
        .account-menu .account-dropdown .btn {
            width: 100%;
            padding: .45rem .7rem;
            font-size: .85rem;
        }
        .account-bar a.account-login {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }
        .account-bar a.account-login:hover { text-decoration: underline; }
        .btn { display: inline-block; background: var(--accent); color: #fff; padding: .5rem 1rem; border-radius: 6px; text-decoration: none; border: 0; cursor: pointer; }
        .btn.secondary { background: #6b7280; }
        .btn.danger { background: #9b2c2c; }
        .card { background: var(--card); border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 1rem; }
        .flash { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .flash.error { background: #fde8e8; color: #8b1a1a; }
        .flash.success { background: #e6f4ea; color: #1e5c33; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #e5e7e2; }
        label { display: block; margin: .5rem 0 .25rem; font-weight: 600; }
        input, select, textarea { width: 100%; max-width: 32rem; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; }
        .muted { color: var(--muted); font-size: .9rem; }
        @media (max-width: 800px) {
            :root { --content-gutter: 1rem; }
            .cup-context { padding-top: .75rem; padding-bottom: .75rem; }
            .account-bar { padding-top: .55rem; padding-bottom: .55rem; }
            .main-inner { padding-top: 1rem; padding-bottom: 1rem; }
            .workspace-tabs { padding-top: .5rem; }
            .account-bar .mode-switch { margin-right: 0; width: 100%; }
            .account-bar .mode-switch summary { width: 100%; }
        }
    </style>
</head>
<body>
<div class="shell">
    <main>
        <header class="account-bar" aria-label="Konto og modus">
            <?php if ($isLoggedIn && $hasCup && $workOptions !== []): ?>
                <?php if ($canSwitchWork): ?>
                    <details class="mode-switch">
                        <summary>
                            <span class="mode-eyebrow">Aktiv modus</span>
                            <span class="mode-current"><?= $h($modeSummary !== '' ? $modeSummary : 'Velg modus') ?></span>
                        </summary>
                        <div class="mode-menu" role="menu">
                            <?php foreach ($workOptions as $opt): ?>
                                <?php
                                $okey = (string) ($opt['key'] ?? '');
                                $olabel = (string) ($opt['label'] ?? '');
                                $odetail = trim((string) ($opt['detail'] ?? ''));
                                $active = $okey === $workKey;
                                ?>
                                <a href="<?= $h($workSwitchBase . rawurlencode($okey)) ?>"
                                   role="menuitem"
                                   class="<?= $active ? 'is-active' : '' ?>"
                                   <?= $active ? 'aria-current="true"' : '' ?>>
                                    <span class="mode-opt-label"><?= $h($olabel) ?></span>
                                    <span class="mode-opt-detail"><?= $h($odetail !== '' ? $odetail : $olabel) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="mode-static">
                        <span class="mode-eyebrow">Aktiv modus</span>
                        <span class="mode-current"><?= $h($modeSummary !== '' ? $modeSummary : $workLabel) ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <details class="account-menu">
                    <summary>
                        <span><?= $h($userName !== '' ? $userName : 'Konto') ?></span>
                        <?php if ($userEmail !== '' && $userEmail !== $userName): ?>
                            <span class="account-email"><?= $h($userEmail) ?></span>
                        <?php endif; ?>
                        <span class="account-chevron" aria-hidden="true">▾</span>
                    </summary>
                    <div class="account-dropdown" role="menu">
                        <?php foreach ($accountLinks as $link): ?>
                            <a href="<?= $h((string) ($link['href'] ?? '')) ?>" role="menuitem">
                                <?= $h((string) ($link['label'] ?? '')) ?>
                            </a>
                        <?php endforeach; ?>
                        <form method="post" action="<?= $h($pp::logout()) ?>">
                            <button type="submit" class="btn secondary">Logg ut</button>
                        </form>
                    </div>
                </details>
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
                <?php if (!$isArrangerMode): ?>
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
                    <?php elseif (count($seasonOptions) === 1): ?>
                        <?php
                        $onlyLabel = trim((string) ($seasonOptions[0]['label'] ?? ''));
                        if ($onlyLabel === '') {
                            $onlyLabel = $seasonLabel;
                        }
                        ?>
                        <span class="season-current" id="season_series_id"><?= $h($onlyLabel !== '' ? $onlyLabel : 'Sesong') ?></span>
                    <?php elseif ($seasonLabel !== ''): ?>
                        <span class="season-current" id="season_series_id"><?= $h($seasonLabel) ?></span>
                    <?php else: ?>
                        <span class="season-current">Ingen sesong</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!$domainBound): ?>
                    <a class="cup-switch" href="<?= $h($pp::cups()) ?>">Bytt <?= $h(strtolower($cupLabel)) ?></a>
                <?php endif; ?>
            </header>
        <?php endif; ?>
        <?php if ($menu !== []): ?>
            <nav class="workspace-tabs" aria-label="Hovedmeny">
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
<script>
(function () {
    document.querySelectorAll('details.mode-switch, details.account-menu').forEach(function (el) {
        el.addEventListener('toggle', function () {
            if (!el.open) return;
            document.querySelectorAll('details.mode-switch, details.account-menu').forEach(function (other) {
                if (other !== el) other.open = false;
            });
        });
    });
})();
</script>
</body>
</html>
