<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var list<array{series_id: int, label: string}> $series_options */
/** @var list<array{org_id: int, name: string}> $organizations */
/** @var int $preset_owner_org_id */
/** @var int $preset_series_id */
/** @var array<string, mixed>|null $event */
/** @var array<string, string> $form_errors */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
$event = is_array($event ?? null) ? $event : [];
$errors = is_array($form_errors ?? null) ? $form_errors : [];
$pp = $pp ?? \App\Support\PortalPaths::class;
$action = $pp::arrangorNyttStevne();
?>
<p><a href="<?= $h($pp::arrangorer()) ?>">← Arrangører</a></p>
<h1>Opprett stevne for ny arrangør</h1>
<p class="muted">
    Oppretter et vanlig stevne i cupen. Organisasjonen blir synlig som arrangør når stevnet er lagret
    (ingen egen arrangør-registrering).
</p>

<div class="card" style="max-width:36rem;">
    <form method="post" action="<?= $h($action) ?>">
        <label for="owner_org_id">Arrangørorganisasjon *</label>
        <select id="owner_org_id" name="owner_org_id" required>
            <option value="">— velg organisasjon —</option>
            <?php foreach ($organizations as $org): ?>
                <?php $oid = (int) ($org['org_id'] ?? 0); ?>
                <option value="<?= $oid ?>" <?= (int) $preset_owner_org_id === $oid ? 'selected' : '' ?>>
                    <?= $h((string) ($org['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (($errors['owner_org_id'] ?? '') !== ''): ?>
            <p class="muted" style="color:#9b2c2c;"><?= $h($errors['owner_org_id']) ?></p>
        <?php endif; ?>

        <label for="series_id">Sesong / serie *</label>
        <select id="series_id" name="series_id" required>
            <option value="">— velg —</option>
            <?php foreach ($series_options as $opt): ?>
                <?php $sid = (int) ($opt['series_id'] ?? 0); ?>
                <option value="<?= $sid ?>" <?= (int) $preset_series_id === $sid ? 'selected' : '' ?>>
                    <?= $h((string) ($opt['label'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (($errors['series_id'] ?? '') !== ''): ?>
            <p class="muted" style="color:#9b2c2c;"><?= $h($errors['series_id']) ?></p>
        <?php endif; ?>

        <label for="name">Stevnenavn *</label>
        <input type="text" id="name" name="name" required value="<?= $h((string) ($event['name'] ?? '')) ?>">
        <?php if (($errors['name'] ?? '') !== ''): ?>
            <p class="muted" style="color:#9b2c2c;"><?= $h($errors['name']) ?></p>
        <?php endif; ?>

        <label for="location_name">Sted</label>
        <input type="text" id="location_name" name="location_name" value="<?= $h((string) ($event['location_name'] ?? '')) ?>">

        <label for="starts_at">Start</label>
        <input type="datetime-local" id="starts_at" name="starts_at" value="<?= $h((string) ($event['starts_at'] ?? '')) ?>">

        <label for="ends_at">Slutt</label>
        <input type="datetime-local" id="ends_at" name="ends_at" value="<?= $h((string) ($event['ends_at'] ?? '')) ?>">

        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['draft', 'active', 'inactive'] as $st): ?>
                <option value="<?= $st ?>" <?= (($event['status'] ?? 'draft') === $st) ? 'selected' : '' ?>><?= $h($st) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="visibility">Synlighet</label>
        <select id="visibility" name="visibility">
            <?php foreach (['internal', 'public', 'private'] as $vis): ?>
                <option value="<?= $vis ?>" <?= (($event['visibility'] ?? 'internal') === $vis) ? 'selected' : '' ?>><?= $h($vis) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description" rows="4"><?= $h((string) ($event['description'] ?? '')) ?></textarea>

        <p style="margin-top:1rem;">
            <button type="submit" class="btn">Opprett stevne</button>
            <a class="btn secondary" href="<?= $h($pp::arrangorer()) ?>">Avbryt</a>
        </p>
    </form>
</div>
