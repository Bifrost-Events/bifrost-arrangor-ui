<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed>|null $series */
/** @var array<string, mixed>|null $parent */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var bool $is_edit */
/** @var bool $is_child */
/** @var bool $show_season_tabs */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
$seriesId = is_array($series) ? (int) ($series['series_id'] ?? 0) : 0;
$parentId = is_array($parent) ? (int) ($parent['series_id'] ?? 0) : 0;
$showTabs = !empty($show_season_tabs);

$pp = $pp ?? \App\Support\PortalPaths::class;
if ($is_edit) {
    $action = $pp::sesongEdit($seriesId);
} elseif ($parentId > 0) {
    $action = $pp::sesongChildren($parentId);
} else {
    $action = $pp::sesonger();
}
?>
<p><a href="<?= $h($pp::cup()) ?>">← <?= $h((string) ($space['name'] ?? '')) ?></a></p>
<h1><?= $is_edit ? 'Rediger' : 'Ny' ?> <?= $h($is_child ? $labels->singular('subseries') : $labels->singular('series')) ?></h1>

<?php if ($showTabs && $is_edit): ?>
    <?php
    $series_id = $seriesId;
    $active_tab = 'oversikt';
    $show_season_tabs = true;
    require __DIR__ . '/_season-tabs.php';
    ?>
<?php endif; ?>

<div class="card" style="max-width:36rem;">
    <form method="post" action="<?= $h($action) ?>">
        <label for="name">Navn *</label>
        <input type="text" id="name" name="name" required value="<?= $h((string) ($series['name'] ?? '')) ?>">

        <label for="short_name">Kortnavn</label>
        <input type="text" id="short_name" name="short_name" value="<?= $h((string) ($series['short_name'] ?? '')) ?>">

        <label for="season_label">Sesong/år</label>
        <input type="text" id="season_label" name="season_label" value="<?= $h((string) ($series['season_label'] ?? '')) ?>">

        <label for="sort_order">Rekkefølge</label>
        <input type="number" id="sort_order" name="sort_order" min="0" value="<?= $h((string) ($series['sort_order'] ?? '')) ?>">

        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['draft', 'active', 'inactive'] as $st): ?>
                <option value="<?= $st ?>" <?= (($series['status'] ?? 'active') === $st) ? 'selected' : '' ?>><?= $h($st) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="visibility">Synlighet</label>
        <select id="visibility" name="visibility">
            <?php foreach (['internal', 'public', 'private'] as $vis): ?>
                <option value="<?= $vis ?>" <?= (($series['visibility'] ?? 'internal') === $vis) ? 'selected' : '' ?>><?= $h($vis) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description" rows="3"><?= $h((string) ($series['description'] ?? '')) ?></textarea>

        <p style="margin-top:1rem;"><button type="submit" class="btn">Lagre</button></p>
    </form>

    <?php if ($is_edit): ?>
        <form method="post" action="<?= $h($pp::sesongArchive($seriesId)) ?>" style="margin-top:1rem;" onsubmit="return confirm('Arkivere denne serien?');">
            <button type="submit" class="btn danger">Arkiver</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($showTabs && $is_edit): ?>
<p class="muted" style="margin-top:1rem;">
    Struktur og sammenlagtregler konfigureres under fanene
    <a href="<?= $h($pp::sesongStruktur($seriesId)) ?>">Struktur</a>
    og
    <a href="<?= $h($pp::sesongSammenlagt($seriesId)) ?>">Sammenlagtregler</a>.
</p>
<?php endif; ?>
