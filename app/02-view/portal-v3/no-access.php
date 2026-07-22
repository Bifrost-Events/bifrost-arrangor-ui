<?php

declare(strict_types=1);

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$message = trim((string) ($message ?? ''));
?>
<div class="card">
    <h1>Ingen tilgang</h1>
    <?php if ($message !== ''): ?>
        <p><?= $h($message) ?></p>
    <?php else: ?>
        <p>Du har ingen organisasjon med administratortilgang i denne cupen.</p>
    <?php endif; ?>
    <p class="muted">Opprett en organisasjon eller søk om å arrangere for å komme i gang.</p>
    <p>
        <a class="btn" href="<?= $h($pp::komIGang()) ?>">Kom i gang</a>
        <a class="btn" href="<?= $h($pp::mineOrganisasjonerNy()) ?>" style="margin-left:.35rem;">Opprett organisasjon</a>
    </p>
</div>
