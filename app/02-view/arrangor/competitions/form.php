<?php

declare(strict_types=1);

use App\Support\CompetitionLimits;

/** @var array<string, mixed>|null $competition */
/** @var array<string, string> $form */
/** @var string $error */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';
$competitionId = is_array($competition) ? (int) ($competition['id'] ?? 0) : 0;
$isEdit = $competitionId > 0;
$action = $isEdit ? '/stevner/' . $competitionId : '/stevner';
$rounds = is_array($context['rounds'] ?? null) ? $context['rounds'] : [];
$selectedRoundId = (int) ($form['round_id'] ?? 0);
$advanceRegistration = ($form['advance_registration_enabled'] ?? '') === '1';
$maxLag = CompetitionLimits::MAX_ANTALL_LAG;
$maxSkillefigur = CompetitionLimits::MAX_SKILLEFIGUR_SKIVE_NR;

$formatRoundPeriod = static function (array $round) use ($h): string {
    $start = trim((string) ($round['start_date'] ?? ''));
    $end = trim((string) ($round['end_date'] ?? ''));
    if ($start === '' && $end === '') {
        return '';
    }
    $fmt = static function (string $iso): string {
        if ($iso === '') {
            return '–';
        }
        $ts = strtotime($iso);

        return $ts !== false ? date('d.m.Y', $ts) : $iso;
    };

    return $fmt($start) . ' – ' . $fmt($end);
};

?>
<h2 style="margin-top:0;"><?= $isEdit ? 'Stevneoppsett' : 'Nytt stevne' ?></h2>
<p class="lead">Sett opp stevneinformasjon, lagoppsett og påmelding.</p>

<?php if ($isEdit): ?>
    <?php
    $competition_id = $competitionId;
    $active_view = 'oppsett';
    include __DIR__ . '/_competition-flow.php';
    ?>
<?php endif; ?>

<?php
$organizer_context = $context;
include __DIR__ . '/../_cup-season-context.php';
?>

<?php if ($error !== ''): ?>
    <p class="form-error" role="alert"><?= $h($error) ?></p>
<?php endif; ?>

<?php if ($rounds === []): ?>
    <div class="placeholder-box">
        <p><strong>Ingen runder i sesongen</strong></p>
        <p class="muted">Cup-administrator må opprette runder før du kan registrere stevner.</p>
    </div>
<?php else: ?>
<style>
    .stevne-form { max-width: 820px; margin-top: 1rem; }
    .stevne-tabs { display: flex; gap: 0.35rem; margin-bottom: 1rem; border-bottom: 1px solid var(--line); padding-bottom: 0.35rem; }
    .stevne-tab-btn {
        border: 1px solid var(--line); background: #fff; border-radius: 4px 4px 0 0;
        padding: 0.45rem 0.85rem; cursor: pointer; font-size: 0.9rem;
    }
    .stevne-tab-btn.is-active { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
    .stevne-tab-panel { display: none; }
    .stevne-tab-panel.is-active { display: block; }
    .stevne-form .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.85rem; margin-bottom: 0.85rem; }
    .stevne-form .form-group-full { grid-column: 1 / -1; }
    .stevne-form label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
    .stevne-form input[type="text"], .stevne-form input[type="date"], .stevne-form input[type="time"],
    .stevne-form input[type="number"], .stevne-form select, .stevne-form textarea {
        width: 100%; padding: 0.45rem 0.55rem; border: 1px solid var(--line); border-radius: 4px; font-size: 0.95rem;
    }
    .form-hint { color: var(--muted); font-size: 0.85rem; margin: 0.25rem 0 0; }
    .checkbox-label { display: flex; align-items: center; gap: 0.45rem; font-weight: 600; }
    .checkbox-label input { width: auto; }
    .lagoppsett-section, .tiebreaker-section {
        margin-top: 1rem; padding: 1rem; border: 1px solid var(--line); border-radius: 6px; background: #fafbf9;
    }
    .lagoppsett-section h3, .tiebreaker-section h3 { margin: 0 0 0.5rem; font-size: 1rem; }
    .lagoversikt-list { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.75rem; }
    .lagoversikt-item {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem;
        padding: 0.45rem 0.65rem; border: 1px solid var(--line); border-radius: 4px; background: #fff;
    }
    .lagoversikt-item .lag-nr { font-weight: 700; min-width: 3.5rem; }
    .lagoversikt-item .lag-tid { color: var(--muted); font-variant-numeric: tabular-nums; }
    .lagoversikt-item .lag-plasser { font-size: 0.85rem; color: var(--muted); }
    .lagoversikt-summary { margin-top: 0.5rem; font-weight: 600; }
    .tiebreaker-list { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
    .tiebreaker-item { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
    .tiebreaker-item select { max-width: 6rem; }
    .advance-dates.is-disabled { opacity: 0.55; pointer-events: none; }
</style>

<form method="post" action="<?= $h($action) ?>" class="stevne-form" id="stevne-form">
    <div class="stevne-tabs" role="tablist" aria-label="Stevneoppsett">
        <button type="button" class="stevne-tab-btn is-active" data-tab="info">Stevneinformasjon</button>
        <button type="button" class="stevne-tab-btn" data-tab="lagoppsett">Lagoppsett og skillefigur</button>
    </div>

    <div class="stevne-tab-panel is-active" data-panel="info">
        <div class="form-row">
            <div class="form-group">
                <label for="round_id">Runde *</label>
                <select id="round_id" name="round_id" required>
                    <option value="">Velg runde</option>
                    <?php foreach ($rounds as $round): ?>
                        <?php if (!is_array($round)) {
                            continue;
                        } ?>
                        <?php
                        $rid = (int) ($round['id'] ?? 0);
                        if ($rid < 1) {
                            continue;
                        }
                        $label = trim(
                            'Runde ' . (int) ($round['round_number'] ?? 0)
                            . ' – ' . (string) ($round['name'] ?? '')
                        );
                        $period = $formatRoundPeriod($round);
                        if ($period !== '') {
                            $label .= ' (' . $period . ')';
                        }
                        ?>
                        <option value="<?= $rid ?>"
                                data-start-date="<?= $h(trim((string) ($round['start_date'] ?? ''))) ?>"
                                data-end-date="<?= $h(trim((string) ($round['end_date'] ?? ''))) ?>"
                            <?= $selectedRoundId === $rid ? ' selected' : '' ?>>
                            <?= $h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Stevnenavn *</label>
                <input type="text" id="name" name="name" value="<?= $h((string) ($form['name'] ?? '')) ?>" required>
            </div>
            <div class="form-group">
                <label for="event_date">Stevnedato</label>
                <input type="date" id="event_date" name="event_date" value="<?= $h((string) ($form['event_date'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="location">Sted</label>
                <input type="text" id="location" name="location" value="<?= $h((string) ($form['location'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="description">Beskrivelse</label>
                <textarea id="description" name="description" rows="3"><?= $h((string) ($form['description'] ?? '')) ?></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="scoring_mode">Resultatformat</label>
                <select id="scoring_mode" name="scoring_mode">
                    <option value="njff"<?= ($form['scoring_mode'] ?? 'njff') === 'njff' ? ' selected' : '' ?>>NJFF (poeng)</option>
                    <option value="dfs"<?= ($form['scoring_mode'] ?? '') === 'dfs' ? ' selected' : '' ?>>DFS (treff/innertreff)</option>
                </select>
                <p class="form-hint">NJFF bruker poengmodell, DFS rangerer på treff/innertreff.</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="invitation_text">Stevneinvitasjon</label>
                <textarea id="invitation_text" name="invitation_text" rows="4" placeholder="Tekst som vises i stevnekalender og på stevnesiden."><?= $h((string) ($form['invitation_text'] ?? '')) ?></textarea>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group form-group-full">
                <label class="checkbox-label">
                    <input type="checkbox" id="advance_registration" name="advance_registration_enabled" value="1"<?= $advanceRegistration ? ' checked' : '' ?>>
                    Tillat forhåndspåmelding på nett
                </label>
                <p class="form-hint">Når av: stevnet vises i kalenderen, men deltakere melder seg ikke på via nett.</p>
            </div>
        </div>
        <div id="advance-registration-dates" class="form-row advance-dates<?= $advanceRegistration ? '' : ' is-disabled' ?>">
            <div class="form-group">
                <label for="registration_start">Påmeldingsstart</label>
                <input type="date" id="registration_start" name="registration_start" value="<?= $h((string) ($form['registration_start'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="registration_end">Påmeldingsslutt</label>
                <input type="date" id="registration_end" name="registration_end" value="<?= $h((string) ($form['registration_end'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_published" name="is_published" value="1"<?= ($form['is_published'] ?? '') === '1' ? ' checked' : '' ?>>
                    Publisert
                </label>
                <p class="form-hint">Publiserte stevner vises i stevnekalenderen.</p>
            </div>
        </div>
    </div>

    <div class="stevne-tab-panel" data-panel="lagoppsett">
        <div class="lagoppsett-section">
            <h3>Lagoppsett</h3>
            <p class="form-hint">Kapasitet = skyttere per lag × antall lag. Reserver lag og enkeltskiver under Stevneadmin etter opprettelse.</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="shooters_per_slot">Skyttere per lag</label>
                    <input type="number" id="shooters_per_slot" name="shooters_per_slot"
                           value="<?= $h((string) ($form['shooters_per_slot'] ?? '6')) ?>" min="1" max="20">
                </div>
                <div class="form-group">
                    <label for="slot_count">Antall lag</label>
                    <input type="number" id="slot_count" name="slot_count"
                           value="<?= $h((string) ($form['slot_count'] ?? '4')) ?>" min="1" max="<?= $maxLag ?>">
                </div>
                <div class="form-group">
                    <label for="first_start_time">Første lags starttid</label>
                    <input type="time" id="first_start_time" name="first_start_time"
                           value="<?= $h((string) ($form['first_start_time'] ?? '09:00')) ?>">
                </div>
                <div class="form-group">
                    <label for="minutes_between_slots">Tid mellom lag (min)</label>
                    <input type="number" id="minutes_between_slots" name="minutes_between_slots"
                           value="<?= $h((string) ($form['minutes_between_slots'] ?? '60')) ?>" min="5" max="180" step="5">
                </div>
            </div>
            <div id="lagoversikt-section">
                <div id="lagoversikt-list" class="lagoversikt-list"></div>
                <p id="lagoversikt-empty" class="form-hint" style="display:none;">Angi antall lag for å se oversikt.</p>
                <p id="lagoversikt-summary" class="lagoversikt-summary"></p>
            </div>
        </div>

        <div class="tiebreaker-section">
            <h3>Skillefigurer</h3>
            <p class="form-hint">Velg antall skillefigurer og rekkefølge (skive 1–<?= $maxSkillefigur ?>).</p>
            <input type="hidden" name="tiebreaker_figure_order" id="tiebreaker_figure_order_input"
                   value="<?= $h((string) ($form['tiebreaker_figure_order'] ?? '[]')) ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="tiebreaker_count">Antall skillefigurer</label>
                    <input type="number" id="tiebreaker_count" min="0" max="20" value="0">
                </div>
            </div>
            <div id="tiebreaker-list" class="tiebreaker-list"></div>
        </div>

        <?php if ($isEdit): ?>
        <div class="form-row" style="margin-top:1rem;">
            <div class="form-group form-group-full">
                <label class="checkbox-label">
                    <input type="checkbox" id="regenerate_slots" name="regenerate_slots" value="1">
                    Regenerer lag og skiver (sletter eksisterende påmeldinger)
                </label>
                <p class="form-hint">Kryss av for å generere lag og skiver på nytt med innstillingene over. Eksisterende påmeldinger fjernes.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="toolbar" style="margin-top:1.25rem;">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lagre stevneoppsett' : 'Opprett stevne' ?></button>
        <a class="btn" href="/stevner">Avbryt</a>
        <?php if ($isEdit): ?>
            <a class="btn" href="/stevner/<?= $competitionId ?>/stevneadmin?vis=pameldelse">Påmelding</a>
            <a class="btn" href="/stevner/<?= $competitionId ?>/stevneadmin?vis=gjennomfor">Gjennomføring</a>
        <?php endif; ?>
    </div>
</form>

<script>
(function() {
    var tabButtons = document.querySelectorAll('.stevne-tab-btn');
    var tabPanels = document.querySelectorAll('.stevne-tab-panel');
    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-tab');
            tabButtons.forEach(function(b) { b.classList.toggle('is-active', b === btn); });
            tabPanels.forEach(function(panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-panel') === tab);
            });
        });
    });

    var advanceCb = document.getElementById('advance_registration');
    var advanceDates = document.getElementById('advance-registration-dates');
    if (advanceCb && advanceDates) {
        advanceCb.addEventListener('change', function() {
            advanceDates.classList.toggle('is-disabled', !advanceCb.checked);
        });
    }

    var roundSelect = document.getElementById('round_id');
    var dateInput = document.getElementById('event_date');
    var regStartInput = document.getElementById('registration_start');
    var regEndInput = document.getElementById('registration_end');

    function selectedRoundDates() {
        if (!roundSelect) {
            return { start: '', end: '' };
        }
        var opt = roundSelect.options[roundSelect.selectedIndex];
        if (!opt || !opt.value) {
            return { start: '', end: '' };
        }
        return {
            start: opt.getAttribute('data-start-date') || '',
            end: opt.getAttribute('data-end-date') || ''
        };
    }

    function competitionAnchorDate() {
        if (dateInput && dateInput.value) {
            return dateInput.value;
        }
        return selectedRoundDates().start || '';
    }

    function bindMonthPicker(input, getAnchor) {
        if (!input || input.dataset.pickerBound) {
            return;
        }
        input.dataset.pickerBound = '1';

        function prime() {
            if (input.value) {
                return;
            }
            var anchor = getAnchor();
            if (!anchor) {
                return;
            }
            input.dataset.pickerProbe = anchor;
            input.value = anchor;
        }

        input.addEventListener('focus', prime);
        input.addEventListener('click', prime);
        input.addEventListener('change', function() {
            input.dataset.pickerCommitted = '1';
            delete input.dataset.pickerProbe;
        });
        input.addEventListener('blur', function() {
            if (input.dataset.pickerCommitted === '1') {
                delete input.dataset.pickerCommitted;
                return;
            }
            if (input.dataset.pickerProbe && input.value === input.dataset.pickerProbe) {
                input.value = '';
            }
            delete input.dataset.pickerProbe;
        });
    }

    if (roundSelect && dateInput) {
        function syncDateBounds() {
            var dates = selectedRoundDates();
            if (dates.start) {
                dateInput.min = dates.start;
            } else {
                dateInput.removeAttribute('min');
            }
            if (dates.end) {
                dateInput.max = dates.end;
            } else {
                dateInput.removeAttribute('max');
            }
        }
        syncDateBounds();
        roundSelect.addEventListener('change', syncDateBounds);
        bindMonthPicker(dateInput, function() {
            return selectedRoundDates().start || '';
        });
    }

    bindMonthPicker(regStartInput, competitionAnchorDate);
    bindMonthPicker(regEndInput, competitionAnchorDate);

    var maxSkillefigur = <?= (int) $maxSkillefigur ?>;
    var tiebreakerFigureOrder = [];
    try {
        tiebreakerFigureOrder = JSON.parse(document.getElementById('tiebreaker_figure_order_input').value || '[]');
        if (!Array.isArray(tiebreakerFigureOrder)) {
            tiebreakerFigureOrder = [];
        }
    } catch (e) {
        tiebreakerFigureOrder = [];
    }
    document.getElementById('tiebreaker_count').value = tiebreakerFigureOrder.length;

    function syncTiebreakerInput() {
        document.getElementById('tiebreaker_figure_order_input').value = JSON.stringify(tiebreakerFigureOrder);
    }

    function updateTiebreakerUi() {
        var countInput = document.getElementById('tiebreaker_count');
        var list = document.getElementById('tiebreaker-list');
        var count = parseInt(countInput.value, 10) || 0;
        if (count < 0) count = 0;
        tiebreakerFigureOrder = tiebreakerFigureOrder.slice(0, count);
        while (tiebreakerFigureOrder.length < count) {
            tiebreakerFigureOrder.push(1);
        }
        tiebreakerFigureOrder = tiebreakerFigureOrder.map(function(n) {
            n = parseInt(n, 10) || 1;
            return Math.max(1, Math.min(maxSkillefigur, n));
        });
        list.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var row = document.createElement('div');
            row.className = 'tiebreaker-item';
            var label = document.createElement('span');
            label.textContent = 'Skillefigur ' + (i + 1) + ':';
            row.appendChild(label);
            var sel = document.createElement('select');
            for (var f = 1; f <= maxSkillefigur; f++) {
                var opt = document.createElement('option');
                opt.value = String(f);
                opt.textContent = 'Skive ' + f;
                if ((tiebreakerFigureOrder[i] || 0) === f) opt.selected = true;
                sel.appendChild(opt);
            }
            sel.setAttribute('data-idx', String(i));
            sel.addEventListener('change', function() {
                var idx = parseInt(this.getAttribute('data-idx'), 10);
                tiebreakerFigureOrder[idx] = parseInt(this.value, 10) || 1;
                syncTiebreakerInput();
            });
            row.appendChild(sel);
            list.appendChild(row);
        }
        syncTiebreakerInput();
    }

    document.getElementById('tiebreaker_count').addEventListener('input', updateTiebreakerUi);
    updateTiebreakerUi();

    function updateLagoversikt() {
        var antallLag = parseInt(document.getElementById('slot_count').value, 10) || 0;
        var firstStart = document.getElementById('first_start_time').value || '09:00';
        var minMellom = parseInt(document.getElementById('minutes_between_slots').value, 10) || 60;
        var antallSkyttere = parseInt(document.getElementById('shooters_per_slot').value, 10) || 0;
        var listEl = document.getElementById('lagoversikt-list');
        var emptyEl = document.getElementById('lagoversikt-empty');
        var summaryEl = document.getElementById('lagoversikt-summary');

        listEl.innerHTML = '';
        if (antallLag < 1) {
            emptyEl.style.display = 'block';
            summaryEl.textContent = '';
            return;
        }
        emptyEl.style.display = 'none';

        var parts = firstStart.split(':');
        var totalMins = (parseInt(parts[0], 10) || 9) * 60 + (parseInt(parts[1], 10) || 0);

        for (var n = 1; n <= antallLag; n++) {
            var mins = totalMins + (n - 1) * minMellom;
            var slotH = Math.floor(mins / 60) % 24;
            var slotM = mins % 60;
            var tidStr = (slotH < 10 ? '0' : '') + slotH + ':' + (slotM < 10 ? '0' : '') + slotM;

            var item = document.createElement('div');
            item.className = 'lagoversikt-item';
            item.innerHTML =
                '<span class="lag-nr">Lag ' + n + '</span>' +
                '<span class="lag-tid">' + tidStr + '</span>' +
                (antallSkyttere > 0 ? '<span class="lag-plasser">' + antallSkyttere + ' plasser</span>' : '');
            listEl.appendChild(item);
        }
        var kapasitet = antallSkyttere > 0 ? antallSkyttere * antallLag : 0;
        summaryEl.textContent = kapasitet > 0
            ? 'Kapasitet: ' + kapasitet + ' skyteplasser (' + antallSkyttere + ' × ' + antallLag + ' lag)'
            : '';
    }

    ['slot_count', 'first_start_time', 'minutes_between_slots', 'shooters_per_slot'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateLagoversikt);
            el.addEventListener('change', updateLagoversikt);
        }
    });
    updateLagoversikt();
})();
</script>
<?php endif; ?>
