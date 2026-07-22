<?php

declare(strict_types=1);

/** @var class-string<\App\Support\PortalPaths> $pp */
/** @var array<string, mixed> $cup_brand */
/** @var bool $submitted */
/** @var string $message */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$brand = is_array($cup_brand ?? null) ? $cup_brand : [];
$logoUrl = trim((string) ($brand['logo_url'] ?? ''));
$cupName = trim((string) ($brand['name'] ?? ''));
$submitted = (bool) ($submitted ?? false);
$message = trim((string) ($message ?? ''));
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
    <h1>Glemt passord</h1>
    <?php if ($submitted): ?>
        <p><?= $h($message !== '' ? $message : 'Hvis e-posten er registrert, har vi sendt en lenke for å sette nytt passord.') ?></p>
        <p class="muted" style="margin-top:1rem;">
            <a href="<?= $h($pp::login()) ?>">Tilbake til innlogging</a>
        </p>
    <?php else: ?>
        <p class="muted">Oppgi e-postadressen til Bifrost-kontoen din. Hvis den er registrert, sender vi en lenke for å sette nytt passord.</p>
        <form method="post" action="<?= $h($pp::glemtPassord()) ?>">
            <label for="email">E-post</label>
            <input type="email" id="email" name="email" required autocomplete="email" autofocus>
            <p style="margin-top:1rem;"><button type="submit" class="btn">Send lenke</button></p>
        </form>
        <p class="muted" style="margin-top:1rem;">
            <a href="<?= $h($pp::login()) ?>">Tilbake til innlogging</a>
        </p>
    <?php endif; ?>
</div>
</div>
