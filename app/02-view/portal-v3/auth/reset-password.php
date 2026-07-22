<?php

declare(strict_types=1);

/** @var class-string<\App\Support\PortalPaths> $pp */
/** @var array<string, mixed> $cup_brand */
/** @var string $token */
/** @var string $error */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$brand = is_array($cup_brand ?? null) ? $cup_brand : [];
$logoUrl = trim((string) ($brand['logo_url'] ?? ''));
$cupName = trim((string) ($brand['name'] ?? ''));
$token = trim((string) ($token ?? ''));
$error = trim((string) ($error ?? ''));
?>
<style>
    .auth-center {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    .pw-panel { max-width: 26rem; width: 100%; }
    .pw-panel .login-logo {
        display: block; max-width: 160px; max-height: 64px; width: auto; height: auto;
        object-fit: contain; margin: 0 0 1rem;
    }
</style>
<div class="auth-center">
<div class="card pw-panel">
    <?php if ($logoUrl !== ''): ?>
        <img class="login-logo" src="<?= $h($logoUrl) ?>" alt="<?= $h($cupName !== '' ? $cupName : 'Cup') ?>">
    <?php endif; ?>
    <h1>Sett nytt passord</h1>

    <?php if ($token === ''): ?>
        <p class="flash error" role="alert"><?= $h($error !== '' ? $error : 'Lenken mangler eller er ugyldig.') ?></p>
        <p class="muted">
            Be om en ny lenke via <a href="<?= $h($pp::glemtPassord()) ?>">Glemt passord</a>.
        </p>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="flash error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>
        <p class="muted">Velg et nytt passord (minst 8 tegn).</p>
        <form method="post" action="<?= $h($pp::tilbakestillPassord()) ?>">
            <input type="hidden" name="token" value="<?= $h($token) ?>">
            <label for="password">Nytt passord</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
            <label for="password_confirm">Bekreft passord</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            <p style="margin-top:1rem;"><button type="submit" class="btn">Lagre passord</button></p>
        </form>
        <p class="muted" style="margin-top:1rem;">
            <a href="<?= $h($pp::login()) ?>">Tilbake til innlogging</a>
        </p>
    <?php endif; ?>
</div>
</div>
