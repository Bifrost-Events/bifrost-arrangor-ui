<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $organizations */
/** @var list<array<string, mixed>> $available_series */
/** @var array<string, mixed> $form */
/** @var array<string, string> $errors */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$selectedOrg = (int) ($form['org_id'] ?? 0);
$selectedSeries = (int) ($form['series_id'] ?? 0);
?>
<p><a href="<?= $h($pp::arrangorSoknader()) ?>">← Arrangørsøknader</a></p>
<h1>Ny arrangørsøknad</h1>
<p class="muted">Søknaden gjelder hele sesongen. Når den er godkjent kan organisasjonen opprette stevner fritt.</p>

<div class="card" style="max-width:36rem;">
    <?php if ($errors !== []): ?>
        <ul style="color:#9b2c2c;">
            <?php foreach ($errors as $field => $msg): ?>
                <li><?= $h((string) $field) ?>: <?= $h((string) $msg) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="<?= $h($pp::arrangorSoknadNy()) ?>">
        <label for="org_id">Organisasjon *</label>
        <select id="org_id" name="org_id" required>
            <?php foreach ($organizations as $org): ?>
                <?php $oid = (int) ($org['org_id'] ?? 0); ?>
                <option value="<?= $oid ?>" <?= $oid === $selectedOrg ? 'selected' : '' ?>>
                    <?= $h((string) ($org['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="series_id">Sesong *</label>
        <select id="series_id" name="series_id" required>
            <option value="">Velg…</option>
            <?php foreach ($available_series as $series): ?>
                <?php
                $sid = (int) ($series['series_id'] ?? 0);
                $accepting = !empty($series['is_accepting']);
                $label = (string) ($series['name'] ?? '');
                if (!empty($series['space_name'])) {
                    $label .= ' · ' . $series['space_name'];
                }
                if (!$accepting) {
                    $label .= ' (stengt)';
                }
                ?>
                <option value="<?= $sid ?>" <?= $sid === $selectedSeries ? 'selected' : '' ?> <?= $accepting ? '' : 'disabled' ?>>
                    <?= $h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="message">Melding til serieeier (valgfritt)</label>
        <textarea id="message" name="message" rows="3"><?= $h((string) ($form['message'] ?? '')) ?></textarea>

        <p style="margin-top:1rem;">
            <button type="submit" class="btn">Lagre utkast</button>
        </p>
    </form>
</div>
