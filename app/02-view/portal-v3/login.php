<?php

declare(strict_types=1);

/** @var class-string<\App\Support\PortalPaths> $pp */
/** @var array<string, mixed> $cup_brand */
/** @var array<string, mixed>|null $domain_application */
/** @var list<array{name: string, url?: string|null, primary_hostname?: string|null}> $shared_login_solutions */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$brand = is_array($cup_brand ?? null) ? $cup_brand : [];
$domain = is_array($domain_application ?? null) ? $domain_application : null;
$sharedSolutions = is_array($shared_login_solutions ?? null) ? $shared_login_solutions : [];

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
    .login-panel .login-help ol,
    .login-panel .login-help ul {
        margin: .35rem 0 .55rem; padding-left: 1.15rem;
    }
    .login-panel .login-help li { margin: .25rem 0; }
    .login-panel .login-help .login-help-note {
        margin: .65rem 0 0; color: #5c5c58; font-size: .85rem;
    }
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
            Arrangørportal for <?= $h($cupName) ?>. Logg inn med Bifrost-brukerkontoen din.
        <?php else: ?>
            Arrangørportal. Logg inn med Bifrost-brukerkontoen din.
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
        <span class="muted">Ny brukerkonto? <a href="<?= $h($pp::komIGang()) ?>"><strong>Kom i gang</strong></a></span>
    </div>

    <div class="login-help" aria-label="Hjelp">
        <p><strong>Slik blir du arrangør</strong></p>
        <ol>
            <li>Logg inn med en eksisterende <strong>brukerkonto</strong>, eller opprett en vanlig brukerkonto.</li>
            <li>Etter innlogging oppretter du en <strong>arrangørprofil</strong>.</li>
            <li>Deretter søker du om <strong>arrangørtilgang</strong> til én eller flere cuper.</li>
            <li>For sesonger som krever godkjenning må søknaden godkjennes før tilgangen aktiveres.</li>
        </ol>
        <p class="login-help-note">
            Brukerkontoen er felles innlogging — den er ikke en separat konto bare for arrangører,
            og den gir ikke automatisk arrangørtilgang til cuper.
        </p>
        <?php if ($sharedSolutions !== []): ?>
            <p>
                Samme <strong>brukerkonto</strong> kan brukes i disse løsningene.
                Arrangørtilgang søkes og godkjennes separat per cup.
            </p>
            <ul>
                <?php foreach ($sharedSolutions as $solution): ?>
                    <?php
                    $solutionName = trim((string) ($solution['name'] ?? ''));
                    if ($solutionName === '') {
                        continue;
                    }
                    $solutionUrl = trim((string) ($solution['url'] ?? ''));
                    ?>
                    <li>
                        <?php if ($solutionUrl !== ''): ?>
                            <a href="<?= $h($solutionUrl) ?>" rel="noopener"><?= $h($solutionName) ?></a>
                        <?php else: ?>
                            <?= $h($solutionName) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
</div>
