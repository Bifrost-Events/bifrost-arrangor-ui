<?php

declare(strict_types=1);

/** @var class-string<\App\Support\PortalPaths> $pp */
/** @var array<string, mixed> $cup_brand */
/** @var array<string, mixed>|null $domain_application */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$brand = is_array($cup_brand ?? null) ? $cup_brand : [];
$domain = is_array($domain_application ?? null) ? $domain_application : null;

$logoUrl = trim((string) ($brand['logo_url'] ?? ''));
$cupName = trim((string) ($brand['name'] ?? ''));
if ($cupName === '') {
    $cupName = trim((string) ($domain['application_name'] ?? ''));
}
$pageHeading = $cupName !== '' ? $cupName : 'Arrangørportal';
?>
<style>
    .auth-center {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    .login-panel { max-width: 26rem; width: 100%; }
    .login-panel .login-logo {
        display: block; max-width: 160px; max-height: 64px; width: auto; height: auto;
        object-fit: contain; margin: 0 0 1rem;
    }
    .login-panel h1 { margin: 0 0 .35rem; font-size: 1.35rem; }
    .login-panel .login-lead { margin: 0 0 1.1rem; }
    .login-panel .login-help {
        margin: 1.25rem 0 0; padding: .85rem 1rem; background: #f7f8f6;
        border: 1px solid #e2e5df; border-radius: 6px; font-size: .9rem;
    }
    .login-panel .login-help p { margin: 0 0 .55rem; }
    .login-panel .login-help p:last-child { margin-bottom: 0; }
    .login-panel .login-actions { margin-top: .75rem; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-size: .9rem; }
    .login-panel .login-actions a { color: var(--accent, #3d6b47); }
</style>
<div class="auth-center">
<div class="card login-panel">
    <?php if ($logoUrl !== ''): ?>
        <img class="login-logo" src="<?= $h($logoUrl) ?>" alt="<?= $h($pageHeading) ?>">
    <?php endif; ?>
    <h1><?= $h($pageHeading) ?></h1>
    <p class="muted login-lead">
        <?php if ($cupName !== ''): ?>
            Arrangørportal for <?= $h($cupName) ?>. Logg inn med Bifrost-kontoen din for å administrere sesonger, runder og stevner.
        <?php else: ?>
            Arrangørportal. Logg inn med Bifrost-kontoen din for å administrere sesonger, runder og stevner.
        <?php endif; ?>
    </p>

    <form method="post" action="<?= $h($pp::login()) ?>">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" required autocomplete="username">
        <label for="password">Passord</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <p style="margin-top:1rem;"><button type="submit" class="btn">Logg inn</button></p>
    </form>

    <div class="login-actions">
        <a href="<?= $h($pp::glemtPassord()) ?>">Glemt passord?</a>
        <span class="muted">Ny arrangør? <a href="<?= $h($pp::komIGang()) ?>"><strong>Kom i gang</strong></a></span>
    </div>

    <div class="login-help" aria-label="Hjelp">
        <p><strong>Har du tilgang?</strong> Bruk e-post og passord for arrangørkontoen din.</p>
        <p><strong>Ny arrangør?</strong> Opprett konto og søk om å arrangere via <a href="<?= $h($pp::komIGang()) ?>">Kom i gang</a>.</p>
        <p class="muted">Mer veiledning kommer snart.</p>
    </div>
</div>
</div>
