<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed> $series */
/** @var array<string, mixed>|null $event */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var bool $is_edit */
/** @var string $route_prefix */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
$seriesId = (int) ($series['series_id'] ?? 0);
$eventId = is_array($event) ? (int) ($event['event_id'] ?? 0) : 0;
$cupName = (string) ($space['name'] ?? '');
$orgName = is_array($event) ? (string) ($event['owner_org_name'] ?? '') : '';
$eventTitle = is_array($event) ? (string) ($event['name'] ?? '') : '';
$pp = $pp ?? \App\Support\PortalPaths::class;
$action = $is_edit
    ? $pp::stevne($eventId)
    : $pp::sesongStevner($seriesId);
?>
<p class="muted" style="margin:0 0 .35rem;">
    <?php if ($cupName !== ''): ?><?= $h($cupName) ?><?php endif; ?>
    <?php if ($orgName !== ''): ?> › <?= $h($orgName) ?><?php endif; ?>
    <?php if ($eventTitle !== ''): ?> › <?= $h($eventTitle) ?><?php endif; ?>
</p>
<p><a href="<?= $h($pp::stevner()) ?>">
    ← <?= $h($labels->plural('event')) ?>
</a></p>
<h1><?= $is_edit ? 'Rediger' : 'Nytt' ?> <?= $h(strtolower($labels->singular('event'))) ?></h1>

<?php if ($is_edit): ?>
<p style="margin:0 0 1rem;">
    <a class="btn secondary" href="<?= $h($pp::stevnePameldinger($eventId)) ?>">Påmeldinger</a>
    <a class="btn secondary" href="<?= $h($pp::stevneJaktfelt($eventId)) ?>">Jaktfelt-grid</a>
</p>
<?php endif; ?>

<div class="card" style="max-width:36rem;">
    <form method="post" action="<?= $h($action) ?>">
        <label for="name">Navn *</label>
        <input type="text" id="name" name="name" required value="<?= $h((string) ($event['name'] ?? '')) ?>">

        <label for="location_name">Sted</label>
        <input type="text" id="location_name" name="location_name" value="<?= $h((string) ($event['location_name'] ?? '')) ?>">

        <label for="starts_at">Start</label>
        <input type="datetime-local" id="starts_at" name="starts_at" value="<?= $h((string) ($event['starts_at'] ?? '')) ?>">

        <label for="ends_at">Slutt</label>
        <input type="datetime-local" id="ends_at" name="ends_at" value="<?= $h((string) ($event['ends_at'] ?? '')) ?>">

        <label for="max_participants">Maks deltakere</label>
        <input type="number" id="max_participants" name="max_participants" min="0" value="<?= $h((string) ($event['max_participants'] ?? '')) ?>">

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

        <p style="margin-top:1rem;"><button type="submit" class="btn">Lagre</button></p>
    </form>

    <?php if ($is_edit): ?>
        <form method="post" action="<?= $h($pp::stevneArchive($eventId)) ?>" style="margin-top:1rem;" onsubmit="return confirm('Arkivere dette arrangementet?');">
            <button type="submit" class="btn danger">Arkiver</button>
        </form>
    <?php endif; ?>
</div>
