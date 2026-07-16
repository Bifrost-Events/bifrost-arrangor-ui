<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed> $series */
/** @var array<string, mixed> $structure_bundle */
/** @var \App\Service\PortalEventTerminology $labels */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$seriesId = (int) ($series['series_id'] ?? 0);
$analysis = is_array($structure_bundle['analysis'] ?? null) ? $structure_bundle['analysis'] : [];
$stored = (string) ($series['structure_type'] ?? '');
$inferred = $analysis['inferred_structure'] ?? null;
$selected = $stored !== '' ? $stored : (string) ($inferred ?? '');
$conflict = !empty($analysis['conflict']);
$canEvents = !empty($analysis['can_save_events']);
$canRounds = !empty($analysis['can_save_rounds']);
$warning = (string) ($analysis['warning'] ?? '');
$previewActual = (string) ($structure_bundle['tree_preview_actual'] ?? '');
$previewEvents = (string) ($structure_bundle['tree_preview_events'] ?? '');
$previewRounds = (string) ($structure_bundle['tree_preview_rounds'] ?? '');
?>
<p><a href="<?= $h($pp::cup()) ?>">← <?= $h((string) ($space['name'] ?? '')) ?></a></p>
<h1><?= $h((string) ($series['name'] ?? $labels->singular('series'))) ?></h1>

<?php
$series_id = $seriesId;
$active_tab = 'struktur';
$show_season_tabs = true;
require __DIR__ . '/_season-tabs.php';
?>

<div class="card" style="max-width:40rem;">
    <h2>Sesongstruktur</h2>
    <p>Hvordan er stevnene organisert?</p>

    <?php if ($conflict || $warning !== ''): ?>
        <div class="form-warning" role="alert"><?= $h($warning !== '' ? $warning : 'Strukturen er i konflikt med eksisterende data.') ?></div>
    <?php endif; ?>

    <p class="muted">Faktisk tre i sesongen (endres ikke automatisk):</p>
    <div class="season-tree-preview"><?= $h($previewActual) ?></div>

    <form method="post" action="<?= $h($pp::sesongStruktur($seriesId)) ?>" id="structure-form">
        <fieldset style="border:0;padding:0;margin:1rem 0 0;">
            <legend class="sr-only">Sesongstruktur</legend>
            <label style="display:block;font-weight:500;margin:.5rem 0;">
                <input type="radio" name="structure_type" value="events" id="struct-events"
                    <?= $selected === 'events' ? ' checked' : '' ?>
                    <?= !$canEvents ? ' disabled' : '' ?>>
                Stevner direkte i sesongen
            </label>
            <p class="muted" style="margin:.25rem 0 .75rem 1.5rem;">Hvert stevne er en egen tellende enhet i sammenlagt.</p>

            <label style="display:block;font-weight:500;margin:.5rem 0;">
                <input type="radio" name="structure_type" value="rounds" id="struct-rounds"
                    <?= $selected === 'rounds' ? ' checked' : '' ?>
                    <?= !$canRounds ? ' disabled' : '' ?>>
                Stevner gruppert i runder
            </label>
            <p class="muted" style="margin:.25rem 0 .75rem 1.5rem;">Flere stevner kan ligge i samme runde. Én tellende verdi velges per runde.</p>
        </fieldset>

        <p><strong>Forhåndsvisning</strong></p>
        <div id="preview-events" class="season-tree-preview" style="<?= $selected === 'rounds' ? 'display:none;' : '' ?>"><?= $h($previewEvents) ?></div>
        <div id="preview-rounds" class="season-tree-preview" style="<?= $selected === 'rounds' ? '' : 'display:none;' ?>"><?= $h($previewRounds) ?></div>

        <?php if (!$canEvents && !$canRounds): ?>
            <p class="form-warning">Lagring er deaktivert til strukturen er ryddet (ikke bland direkte stevner og stevner under runder).</p>
        <?php endif; ?>

        <p style="margin-top:1rem;">
            <button type="submit" class="btn" id="structure-save" <?= (!$canEvents && !$canRounds) ? ' disabled' : '' ?>>Lagre struktur</button>
            <span class="unsaved-hint" id="structure-unsaved">Ikke lagret</span>
        </p>
    </form>
</div>
<script>
(function () {
    var events = document.getElementById('struct-events');
    var rounds = document.getElementById('struct-rounds');
    var pe = document.getElementById('preview-events');
    var pr = document.getElementById('preview-rounds');
    var unsaved = document.getElementById('structure-unsaved');
    var initial = <?= json_encode($stored !== '' ? $stored : $selected, JSON_UNESCAPED_UNICODE) ?>;
    function sync() {
        var v = (rounds && rounds.checked) ? 'rounds' : 'events';
        if (pe) pe.style.display = v === 'rounds' ? 'none' : '';
        if (pr) pr.style.display = v === 'rounds' ? '' : 'none';
        if (unsaved) unsaved.classList.toggle('is-visible', v !== initial);
    }
    if (events) events.addEventListener('change', sync);
    if (rounds) rounds.addEventListener('change', sync);
    sync();
})();
</script>
