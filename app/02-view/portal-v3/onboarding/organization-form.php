<?php

declare(strict_types=1);

/** @var array<string, mixed> $form */
/** @var array<string, string> $errors */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
?>
<p><a href="<?= $h($pp::mineOrganisasjoner()) ?>">← Mine organisasjoner</a></p>
<h1>Ny organisasjon</h1>
<p class="muted">Du blir eier (org_owner) når organisasjonen opprettes.</p>

<div class="card" style="max-width:36rem;">
    <?php if ($errors !== []): ?>
        <ul class="muted" style="color:#9b2c2c;">
            <?php foreach ($errors as $field => $msg): ?>
                <li><?= $h((string) $field) ?>: <?= $h((string) $msg) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="<?= $h($pp::mineOrganisasjonerNy()) ?>">
        <label for="name">Navn *</label>
        <input type="text" id="name" name="name" required value="<?= $h((string) ($form['name'] ?? '')) ?>">

        <label for="organization_number">Organisasjonsnummer</label>
        <input type="text" id="organization_number" name="organization_number" value="<?= $h((string) ($form['organization_number'] ?? '')) ?>">

        <label for="email">E-post</label>
        <input type="email" id="email" name="email" value="<?= $h((string) ($form['email'] ?? '')) ?>">

        <label for="phone">Telefon</label>
        <input type="text" id="phone" name="phone" value="<?= $h((string) ($form['phone'] ?? '')) ?>">

        <label for="website">Nettside</label>
        <input type="url" id="website" name="website" value="<?= $h((string) ($form['website'] ?? '')) ?>">

        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description" rows="3"><?= $h((string) ($form['description'] ?? '')) ?></textarea>

        <p style="margin-top:1rem;">
            <button type="submit" class="btn">Opprett</button>
        </p>
    </form>
</div>
