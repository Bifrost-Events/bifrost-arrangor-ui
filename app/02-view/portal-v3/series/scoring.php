<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed> $series */
/** @var array<string, mixed> $structure_bundle */
/** @var \App\Service\PortalEventTerminology $labels */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$seriesId = (int) ($series['series_id'] ?? 0);
$structureType = (string) ($series['structure_type'] ?? '');
$scoring = is_array($structure_bundle['scoring'] ?? null) ? $structure_bundle['scoring'] : [];
$config = is_array($scoring['config'] ?? null) ? $scoring['config'] : null;
$source = (string) ($scoring['source'] ?? 'none');
$notes = is_array($scoring['notes'] ?? null) ? $scoring['notes'] : [];
$analysis = is_array($structure_bundle['analysis'] ?? null) ? $structure_bundle['analysis'] : [];
$conflict = !empty($analysis['conflict']);
$warning = (string) ($analysis['warning'] ?? '');

$isRounds = $structureType === 'rounds';
$isEvents = $structureType === 'events';
$structureReady = $isRounds || $isEvents;

$selection = (int) ($config['selection_count'] ?? 3);
$minimum = (int) ($config['minimum_participation'] ?? 0);
$valueSource = (string) ($config['value_source'] ?? 'raw_score');
if ($valueSource !== 'placement_points') {
    $valueSource = 'raw_score';
}
$summary = (string) ($scoring['summary'] ?? '');
$storedPts = is_array($config['placement_points'] ?? null) ? $config['placement_points'] : [];
if ($storedPts === [] && is_array($series['cup_placement_points'] ?? null)) {
    $storedPts = $series['cup_placement_points'];
}
$defaultPts = [
    1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10,
    6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1,
];
$maxPlace = 25;

$canSave = $structureReady && !$conflict
    && (
        ($isEvents && !empty($analysis['can_save_events']))
        || ($isRounds && !empty($analysis['can_save_rounds']))
    );
$fromLegacy = str_starts_with($source, 'legacy_');
?>
<p><a href="<?= $h($pp::cup()) ?>">← <?= $h((string) ($space['name'] ?? '')) ?></a></p>
<h1><?= $h((string) ($series['name'] ?? $labels->singular('series'))) ?></h1>

<?php
$series_id = $seriesId;
$active_tab = 'sammenlagt';
$show_season_tabs = true;
require __DIR__ . '/_season-tabs.php';
?>

<div class="card" style="max-width:42rem;">
    <h2>Sammenlagtregler</h2>
    <p class="muted">
        Stevnebasert sammenlagt summerer verdier fra enkeltstevner.
        Rundebasert sammenlagt velger først beste verdi i hver runde, og summerer deretter de beste rundene.
    </p>

    <?php if (!$structureReady): ?>
        <div class="form-warning" role="alert">
            Sett <a href="<?= $h($pp::sesongStruktur($seriesId)) ?>">sesongstruktur</a> før sammenlagtregler kan lagres.
            <?php if ($fromLegacy): ?>
                Viser verdier fra tidligere innstillinger (ikke lagret i ny modell ennå).
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($conflict || $warning !== ''): ?>
        <div class="form-warning" role="alert"><?= $h($warning !== '' ? $warning : 'Strukturkonflikt blokkerer lagring.') ?></div>
    <?php endif; ?>

    <?php if ($fromLegacy && $structureReady && !$conflict): ?>
        <p class="muted">Verdier er hentet fra tidligere innstillinger. Lagre for å aktivere dem i ny modell.</p>
    <?php endif; ?>

    <form method="post" action="<?= $h($pp::sesongSammenlagt($seriesId)) ?>" id="scoring-form">
        <p><strong>Sammenlagt beregnes fra:</strong>
            <?= $isRounds ? 'Beste runder' : 'Beste stevner' ?>
        </p>

        <?php if ($isRounds): ?>
            <label for="selection_count">Antall tellende runder</label>
            <input type="number" id="selection_count" name="selection_count" min="1" max="99" value="<?= $selection ?>" <?= $canSave ? '' : ' disabled' ?>>

            <p style="margin-top:.75rem;"><strong>Resultat innen samme runde:</strong> Beste resultat teller</p>

            <label for="minimum_participation">Minimum antall runder for å komme med i sammenlagt</label>
            <input type="number" id="minimum_participation" name="minimum_participation" min="0" max="99" value="<?= $minimum ?>" placeholder="0" <?= $canSave ? '' : ' disabled' ?>>
        <?php else: ?>
            <label for="selection_count">Antall tellende stevner</label>
            <input type="number" id="selection_count" name="selection_count" min="1" max="99" value="<?= $selection ?>" <?= $canSave ? '' : ' disabled' ?>>

            <label for="minimum_participation">Minimum antall stevner for å komme med i sammenlagt</label>
            <input type="number" id="minimum_participation" name="minimum_participation" min="0" max="99" value="<?= $minimum ?>" placeholder="0" <?= $canSave ? '' : ' disabled' ?>>
        <?php endif; ?>

        <p style="margin-top:1rem;"><strong>Verdi som summeres</strong></p>
        <label style="display:block;font-weight:500;margin:.35rem 0;">
            <input type="radio" name="value_source" value="raw_score" id="value-raw"
                <?= $valueSource === 'raw_score' ? ' checked' : '' ?>
                <?= $canSave ? '' : ' disabled' ?>>
            Faktisk stevneresultat
        </label>
        <label style="display:block;font-weight:500;margin:.35rem 0;">
            <input type="radio" name="value_source" value="placement_points" id="value-placement"
                <?= $valueSource === 'placement_points' ? ' checked' : '' ?>
                <?= $canSave ? '' : ' disabled' ?>>
            Cup-poeng etter plassering
        </label>

        <div id="placement-points-block" style="<?= $valueSource === 'placement_points' ? '' : 'display:none;' ?>">
            <p style="margin-top:.75rem;"><strong>Poeng per plassering</strong> (1–<?= $maxPlace ?>). Tom = 0.</p>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr)); gap:.5rem;">
                <?php for ($pl = 1; $pl <= $maxPlace; $pl++): ?>
                    <?php
                    $keyHas = array_key_exists($pl, $storedPts) || array_key_exists((string) $pl, $storedPts);
                    $val = $keyHas ? ($storedPts[$pl] ?? $storedPts[(string) $pl]) : null;
                    if (!$keyHas && $valueSource === 'placement_points' && $storedPts === []) {
                        $val = $defaultPts[$pl] ?? null;
                    }
                    $disp = $val !== null && $val !== '' ? (string) $val : '';
                    ?>
                    <label style="font-weight:500;">
                        <?= $pl ?>.
                        <input type="number" class="placement-point-input" name="placement_points[<?= $pl ?>]" value="<?= $h($disp) ?>" step="0.001" min="0" style="max-width:100%;" <?= $canSave ? '' : ' disabled' ?>>
                    </label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="scoring-summary" id="scoring-summary"><?= $h($summary !== '' ? $summary : '—') ?></div>
        <p class="muted" id="min-warning" style="display:none;color:#9b2c2c;">Minimum deltakelse kan ikke være høyere enn antall tellende.</p>

        <p style="margin-top:1rem;">
            <button type="submit" class="btn" <?= $canSave ? '' : ' disabled' ?>>Lagre sammenlagtregler</button>
            <span class="unsaved-hint" id="scoring-unsaved">Ikke lagret</span>
        </p>
    </form>
</div>
<script>
(function () {
    var isRounds = <?= $isRounds ? 'true' : 'false' ?>;
    var sel = document.getElementById('selection_count');
    var min = document.getElementById('minimum_participation');
    var summary = document.getElementById('scoring-summary');
    var minWarn = document.getElementById('min-warning');
    var unsaved = document.getElementById('scoring-unsaved');
    var valueRaw = document.getElementById('value-raw');
    var valuePlacement = document.getElementById('value-placement');
    var pointsBlock = document.getElementById('placement-points-block');
    var initialSel = <?= (int) $selection ?>;
    var initialMin = <?= (int) $minimum ?>;
    var initialValue = <?= json_encode($valueSource, JSON_UNESCAPED_UNICODE) ?>;
    function isPlacement() {
        return !!(valuePlacement && valuePlacement.checked);
    }
    function text() {
        var n = Math.max(1, parseInt(sel && sel.value ? sel.value : '1', 10) || 1);
        if (isRounds) {
            if (isPlacement()) {
                return 'Hvert stevne gir cup-poeng etter plassering. Skytterens beste resultat i hver runde velges. De ' + n + ' beste rundene summeres.';
            }
            return 'Skytterens beste resultat i hver runde velges. De ' + n + ' beste rundene summeres.';
        }
        if (isPlacement()) {
            return 'Hvert stevne gir cup-poeng etter plassering. Skytterens ' + n + ' beste stevner summeres.';
        }
        return 'Skytterens ' + n + ' beste stevner summeres.';
    }
    function sync() {
        var n = Math.max(1, parseInt(sel && sel.value ? sel.value : '1', 10) || 1);
        var m = Math.max(0, parseInt(min && min.value !== '' ? min.value : '0', 10) || 0);
        var v = isPlacement() ? 'placement_points' : 'raw_score';
        if (pointsBlock) pointsBlock.style.display = v === 'placement_points' ? '' : 'none';
        if (summary) summary.textContent = text();
        if (minWarn) minWarn.style.display = m > n ? '' : 'none';
        if (unsaved) unsaved.classList.toggle('is-visible', n !== initialSel || m !== initialMin || v !== initialValue);
    }
    if (sel) sel.addEventListener('input', sync);
    if (min) min.addEventListener('input', sync);
    if (valueRaw) valueRaw.addEventListener('change', sync);
    if (valuePlacement) valuePlacement.addEventListener('change', sync);
    sync();
})();
</script>
