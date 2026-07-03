<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $error */
/** @var string $public_register_url */
/** @var array{resolved: bool, cup_name: string, season_label: string, host: string, error: string|null} $portal */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';
$publicRegisterUrl = trim($public_register_url ?? '');
$portal = is_array($portal ?? null) ? $portal : [
    'resolved' => false,
    'cup_name' => '',
    'season_label' => '',
    'host' => '',
    'error' => null,
];
$cupName = trim((string) ($portal['cup_name'] ?? ''));
$seasonLabel = trim((string) ($portal['season_label'] ?? ''));
$pageTitle = $cupName !== '' ? $cupName . ' – Arrangør' : 'Bifrost Arrangør';

?>
<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title) ?> – <?= $h($pageTitle) ?></title>
    <style>
        :root { --bg: #f4f4f2; --ink: #1a1a18; --muted: #5c5c58; --line: #d8d8d4; --accent: #2c5530; --bad: #9b2c2c; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
        .login-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.5rem; width: 100%; max-width: 420px; }
        h1 { margin: 0 0 0.35rem; font-size: 1.25rem; }
        .login-subtitle { color: var(--muted); font-size: 0.9rem; margin: 0 0 1rem; }
        .portal-context {
            margin: 0 0 1rem; padding: 0.65rem 0.75rem; background: #f4f7f4;
            border: 1px solid #d8e3da; border-radius: 6px; font-size: 0.9rem;
        }
        .portal-context__row { display: flex; gap: 0.4rem; align-items: baseline; margin: 0.15rem 0; }
        .portal-context__label {
            color: var(--muted); font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em; min-width: 3.5rem;
        }
        .portal-context__warn { color: var(--bad); font-size: 0.85rem; margin: 0; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.35rem; }
        input { width: 100%; padding: 0.55rem 0.65rem; border: 1px solid var(--line); border-radius: 4px; font-size: 1rem; margin-bottom: 0.9rem; }
        button { width: 100%; padding: 0.65rem 1rem; background: var(--accent); color: #fff; border: none; border-radius: 4px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .error { background: #fdeaea; border: 1px solid #f0c4c4; color: var(--bad); padding: 0.65rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .register-link { margin-top: 1rem; font-size: 0.9rem; text-align: center; }
        .register-link a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="login-card">
        <h1><?= $h($pageTitle) ?></h1>
        <p class="login-subtitle">Arrangørportal<?= $cupName !== '' ? ' for ' . $h($cupName) : '' ?>. Logg inn med Bifrost-kontoen din.</p>

        <?php if ($cupName !== '' || $seasonLabel !== ''): ?>
            <div class="portal-context" aria-label="Cup og sesong">
                <?php if ($cupName !== ''): ?>
                    <div class="portal-context__row">
                        <span class="portal-context__label">Cup</span>
                        <strong><?= $h($cupName) ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($seasonLabel !== ''): ?>
                    <div class="portal-context__row">
                        <span class="portal-context__label">Sesong</span>
                        <strong><?= $h($seasonLabel) ?></strong>
                    </div>
                <?php elseif ($portal['resolved'] ?? false): ?>
                    <p class="portal-context__warn">Ingen aktiv sesong er satt opp for denne cupen ennå.</p>
                <?php endif; ?>
            </div>
        <?php elseif (($portal['error'] ?? '') !== ''): ?>
            <p class="portal-context portal-context__warn"><?= $h((string) $portal['error']) ?></p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login">
            <label for="email">E-post</label>
            <input type="email" id="email" name="email" required autocomplete="email" autofocus>

            <label for="password">Passord</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit">Logg inn</button>
        </form>

        <?php if ($publicRegisterUrl !== ''): ?>
            <p class="register-link">
                Har du ikke konto? <a href="<?= $h($publicRegisterUrl) ?>">Registrer deg</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
