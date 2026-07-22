<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var list<array<string, mixed>> $roots */
/** @var array<int, list<array<string, mixed>>> $children */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var bool $can_edit_space */
/** @var bool $can_manage_series */
/** @var bool $can_create_series */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$spaceId = (int) ($space['space_id'] ?? 0);

$structureLabel = static function (string $type) use ($labels): string {
    return match ($type) {
        'events' => $labels->plural('event') . ' direkte i ' . strtolower($labels->singular('series')),
        'rounds' => $labels->plural('event') . ' gruppert i ' . strtolower($labels->plural('subseries')),
        default => 'Struktur ikke valgt',
    };
};

$toDateInput = static function (mixed $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
        return $m[1];
    }
    $ts = strtotime($raw);

    return $ts !== false ? date('Y-m-d', $ts) : '';
};

$formatPeriod = static function (array $row) use ($toDateInput): string {
    $fromRaw = $toDateInput($row['starts_at'] ?? null);
    $toRaw = $toDateInput($row['ends_at'] ?? null);
    $nb = static function (string $ymd): string {
        if ($ymd === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return $ymd;
        }

        return $m[3] . '.' . $m[2] . '.' . $m[1];
    };
    $from = $nb($fromRaw);
    $to = $nb($toRaw);
    if ($from === '' && $to === '') {
        return '';
    }
    if ($from !== '' && $to !== '') {
        return $from . ' – ' . $to;
    }

    return $from !== '' ? ('fra ' . $from) : ('til ' . $to);
};
?>
<style>
    .rounds-matrix {
        width: 100%;
        border-collapse: collapse;
        margin-top: .75rem;
    }
    .rounds-matrix th,
    .rounds-matrix td {
        text-align: left;
        padding: .45rem .4rem;
        vertical-align: middle;
        border-bottom: 1px solid #e6e8e4;
    }
    .rounds-matrix th {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--muted, #5c635c);
    }
    .rounds-matrix input[type="text"],
    .rounds-matrix input[type="date"],
    .rounds-matrix input[type="number"] {
        width: 100%;
        box-sizing: border-box;
        max-width: 14rem;
    }
    .rounds-matrix input[type="number"] { max-width: 4.5rem; }
    .rounds-matrix input[type="date"] { max-width: 10.5rem; }
    .season-period-row {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem 1.25rem;
        align-items: end;
        margin: .75rem 0 .25rem;
    }
    .season-period-row label {
        display: block;
        font-size: .82rem;
        margin-bottom: .2rem;
    }
    .matrix-actions {
        margin-top: .85rem;
        display: flex;
        flex-wrap: wrap;
        gap: .65rem;
        align-items: center;
    }
    .matrix-hint {
        font-size: .85rem;
        color: var(--muted, #5c635c);
        margin: .35rem 0 0;
    }
    .matrix-row-error {
        display: none;
        margin: .35rem 0 .15rem;
        padding: .55rem .75rem;
        border-radius: 6px;
        background: #fde8e8;
        color: #8b1a1a;
        font-size: .9rem;
        font-weight: 600;
        border: 1px solid #f0b4b4;
    }
    .matrix-row-error.is-visible { display: block; }
    .rounds-matrix tr.has-error td {
        background: #fff5f5;
    }
    .rounds-matrix tr.has-error input[type="date"] {
        border-color: #c53030;
        outline-color: #c53030;
    }
    .season-period-row.has-error {
        padding: .5rem .65rem;
        margin-left: -.65rem;
        margin-right: -.65rem;
        border-radius: 6px;
        background: #fff5f5;
    }
    .season-period-row.has-error input[type="date"] {
        border-color: #c53030;
    }
    .rounds-matrix .js-round-error-cell {
        padding-top: 0;
        border-bottom: 1px solid #e6e8e4;
    }
    .rounds-batch-create {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .65rem 1rem;
        margin: .85rem 0 .35rem;
    }
    .rounds-batch-create label {
        display: block;
        font-size: .85rem;
        font-weight: 600;
        margin-bottom: .25rem;
    }
    .rounds-batch-create input[type="number"] {
        width: 5.5rem;
        padding: .45rem .5rem;
    }
    .rounds-batch-create .matrix-hint {
        flex: 1 1 100%;
        margin: 0;
    }
    .rounds-matrix tr.js-round-error-row td {
        border-bottom: 1px solid #e6e8e4;
    }
    .rounds-matrix tr.js-round-error-row:not(.is-visible) {
        display: none;
    }
</style>

<h1><?= $h($labels->plural('series')) ?></h1>
<p class="muted"><?= $h($labels->plural('series')) ?> og <?= $h($labels->plural('subseries')) ?> i denne <?= $h(strtolower($labels->singular('event_space'))) ?></p>

<?php if ($can_edit_space): ?>
    <p>
        <a class="btn secondary" href="<?= $h($pp::cupEdit()) ?>">Rediger <?= $h(strtolower($labels->singular('event_space'))) ?></a>
        <a class="btn secondary" href="<?= $h($pp::stevner()) ?>">Alle <?= $h(strtolower($labels->plural('event'))) ?></a>
    </p>
<?php endif; ?>

<?php if ($can_create_series): ?>
    <p><a class="btn" href="<?= $h($pp::sesongNew()) ?>">
        Ny <?= $h(strtolower($labels->singular('series'))) ?>
    </a></p>
<?php endif; ?>

<?php foreach ($roots as $root): ?>
    <?php
    $rootId = (int) ($root['series_id'] ?? 0);
    $structure = (string) ($root['structure_type'] ?? '');
    $subs = $children[$rootId] ?? [];
    $rootPeriod = $formatPeriod($root);
    $seasonFrom = $toDateInput($root['starts_at'] ?? null);
    $seasonTo = $toDateInput($root['ends_at'] ?? null);
    ?>
    <div class="card" id="season-<?= $rootId ?>">
        <h2>
            <?= $h((string) ($root['name'] ?? '')) ?>
            <?php if ($can_manage_series): ?>
                <a class="btn secondary" style="font-size:.85rem;margin-left:.5rem;" href="<?= $h($pp::sesongEdit($rootId)) ?>">Rediger</a>
            <?php endif; ?>
        </h2>
        <p class="muted">
            <?= $h($labels->singular('series')) ?>
            · <?= $h($structureLabel($structure)) ?>
            <?php if ($rootPeriod !== '' && !($can_manage_series && $structure === 'rounds')): ?>
                · <?= $h($rootPeriod) ?>
            <?php endif; ?>
            <?php if ($can_manage_series): ?>
                · <a href="<?= $h($pp::sesongStruktur($rootId)) ?>">Struktur</a>
            <?php endif; ?>
        </p>

        <?php if ($structure === ''): ?>
            <p class="muted">Velg om sesongen skal ha stevner direkte, eller stevner gruppert i runder, før du bygger opp innholdet.</p>
            <?php if ($can_manage_series): ?>
                <p><a class="btn" href="<?= $h($pp::sesongStruktur($rootId)) ?>">Sett struktur</a></p>
            <?php endif; ?>

        <?php elseif ($structure === 'events'): ?>
            <p>
                <a class="btn" href="<?= $h($pp::sesongStevner($rootId)) ?>">
                    <?= $h($labels->plural('event')) ?>
                </a>
                <?php if ($can_manage_series): ?>
                    <a class="btn secondary" href="<?= $h($pp::sesongStevneNew($rootId)) ?>">
                        Nytt <?= $h(strtolower($labels->singular('event'))) ?>
                    </a>
                <?php endif; ?>
            </p>
            <?php if ($subs !== []): ?>
                <p class="muted" role="status">
                    Sesongen har <?= $h(strtolower($labels->plural('subseries'))) ?> selv om strukturen er «stevner direkte».
                    Rydd opp under <a href="<?= $h($pp::sesongStruktur($rootId)) ?>">Struktur</a>.
                </p>
                <ul>
                <?php foreach ($subs as $sub): ?>
                    <?php
                    $subId = (int) ($sub['series_id'] ?? 0);
                    $subPeriod = $formatPeriod($sub);
                    ?>
                    <li>
                        <strong><?= $h((string) ($sub['name'] ?? '')) ?></strong>
                        <span class="muted">(<?= $h($labels->singular('subseries')) ?>)</span>
                        <?php if ($subPeriod !== ''): ?>
                            <span class="muted">· <?= $h($subPeriod) ?></span>
                        <?php endif; ?>
                        <?php if ($can_manage_series): ?>
                            <a href="<?= $h($pp::sesongEdit($subId)) ?>">Rediger</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: /* rounds */ ?>
            <?php if ($can_manage_series): ?>
                <form method="post" action="<?= $h($pp::sesongRoundsMatrix($rootId)) ?>" class="rounds-matrix-form"
                      data-season-from="<?= $h($seasonFrom) ?>" data-season-to="<?= $h($seasonTo) ?>">
                    <div class="season-period-row">
                        <div>
                            <label for="season-<?= $rootId ?>-from">Sesong fra</label>
                            <input type="date" id="season-<?= $rootId ?>-from" name="season[starts_on]"
                                   value="<?= $h($seasonFrom) ?>" class="js-season-from">
                        </div>
                        <div>
                            <label for="season-<?= $rootId ?>-to">Sesong til</label>
                            <input type="date" id="season-<?= $rootId ?>-to" name="season[ends_on]"
                                   value="<?= $h($seasonTo) ?>" class="js-season-to">
                        </div>
                    </div>
                    <p class="js-season-error matrix-row-error" role="alert"></p>
                    <p class="matrix-hint">
                        Rundedatoer må ligge innenfor sesongen og ikke overlappe hverandre.
                        Tomme felt foreslås: første runde starter med sesongstart, neste runde dagen etter forrige sluttdato, siste runde slutter med sesongslutt.
                    </p>

                    <?php if ($subs === []): ?>
                        <p class="muted">Ingen <?= $h(strtolower($labels->plural('subseries'))) ?> ennå.</p>
                        <div class="rounds-batch-create">
                            <label for="round-count-<?= $rootId ?>">Antall <?= $h(strtolower($labels->plural('subseries'))) ?></label>
                            <input type="number" id="round-count-<?= $rootId ?>" name="round_count"
                                   min="1" max="24" value="6" required
                                   aria-describedby="round-count-hint-<?= $rootId ?>">
                            <button type="submit" class="btn"
                                    formaction="<?= $h($pp::sesongRoundsBatchCreate($rootId)) ?>">
                                Opprett
                            </button>
                            <p id="round-count-hint-<?= $rootId ?>" class="matrix-hint">
                                Oppretter <?= $h(strtolower($labels->plural('subseries'))) ?> med jevnt fordelte datoer innenfor sesongen.
                                Du kan justere navn og datoer etterpå.
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="rounds-matrix">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Navn</th>
                                    <th>Fra</th>
                                    <th>Til</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($subs as $sub): ?>
                                <?php
                                $subId = (int) ($sub['series_id'] ?? 0);
                                $sort = (string) ($sub['sort_order'] ?? '');
                                ?>
                                <tr class="js-round-data-row">
                                    <td>
                                        <input type="number" name="rounds[<?= $subId ?>][sort_order]" min="0"
                                               value="<?= $h($sort) ?>" aria-label="Rekkefølge">
                                    </td>
                                    <td>
                                        <input type="text" name="rounds[<?= $subId ?>][name]" required
                                               value="<?= $h((string) ($sub['name'] ?? '')) ?>"
                                               aria-label="Navn">
                                    </td>
                                    <td>
                                        <input type="date" name="rounds[<?= $subId ?>][starts_on]"
                                               class="js-round-from"
                                               value="<?= $h($toDateInput($sub['starts_at'] ?? null)) ?>"
                                               aria-label="Fra dato">
                                    </td>
                                    <td>
                                        <input type="date" name="rounds[<?= $subId ?>][ends_on]"
                                               class="js-round-to"
                                               value="<?= $h($toDateInput($sub['ends_at'] ?? null)) ?>"
                                               aria-label="Til dato">
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <a href="<?= $h($pp::sesongStevner($subId)) ?>"><?= $h($labels->plural('event')) ?></a>
                                        ·
                                        <a href="<?= $h($pp::sesongStevneNew($subId)) ?>">Nytt</a>
                                    </td>
                                </tr>
                                <tr class="js-round-error-row" aria-hidden="true">
                                    <td colspan="5" class="js-round-error-cell">
                                        <div class="matrix-row-error js-round-error" role="alert"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <div class="matrix-actions">
                        <?php if ($subs !== []): ?>
                            <button type="submit" class="btn">Lagre perioder</button>
                            <a class="btn secondary" href="<?= $h($pp::sesongChildNew($rootId)) ?>">
                                Ny <?= $h(strtolower($labels->singular('subseries'))) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($subs === []): ?>
                    <p class="muted">Ingen <?= $h(strtolower($labels->plural('subseries'))) ?> ennå.</p>
                <?php else: ?>
                    <ul>
                    <?php foreach ($subs as $sub): ?>
                        <?php
                        $subId = (int) ($sub['series_id'] ?? 0);
                        $subPeriod = $formatPeriod($sub);
                        ?>
                        <li>
                            <strong><?= $h((string) ($sub['name'] ?? '')) ?></strong>
                            <?php if ($subPeriod !== ''): ?>
                                <span class="muted">· <?= $h($subPeriod) ?></span>
                            <?php endif; ?>
                            <a class="btn" href="<?= $h($pp::sesongStevner($subId)) ?>">
                                <?= $h($labels->plural('event')) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($roots === []): ?>
    <div class="card"><p>Ingen <?= $h(strtolower($labels->plural('series'))) ?> i dette space.</p></div>
<?php endif; ?>

<script>
(function () {
    function val(el) { return (el && el.value || '').trim(); }

    function addDays(ymd, days) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return '';
        var parts = ymd.split('-').map(Number);
        var d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setDate(d.getDate() + days);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function isSuggested(el) {
        return el && el.dataset.suggested === '1' && val(el) === (el.dataset.suggestedValue || '');
    }

    function setSuggested(el, value) {
        if (!el || !value) return;
        var current = val(el);
        if (current !== '' && !isSuggested(el)) return;
        if (current === value && isSuggested(el)) return;
        el.value = value;
        el.dataset.suggested = '1';
        el.dataset.suggestedValue = value;
    }

    function clearSuggested(el) {
        if (!el || !isSuggested(el)) return;
        el.value = '';
        delete el.dataset.suggested;
        delete el.dataset.suggestedValue;
    }

    function markManualIfChanged(el) {
        if (!el) return;
        if (el.dataset.suggested === '1' && val(el) !== (el.dataset.suggestedValue || '')) {
            delete el.dataset.suggested;
            delete el.dataset.suggestedValue;
        }
    }

    function setErrorBox(el, messages) {
        if (!el) return;
        if (messages && messages.length) {
            el.textContent = messages.join(' ');
            el.classList.add('is-visible');
        } else {
            el.textContent = '';
            el.classList.remove('is-visible');
        }
    }

    /**
     * Native date-input åpner ellers ofte på inneværende måned når feltet er tomt,
     * eller på sluttdatoens måned. Ved valg av til-dato: åpne i fra-datoens måned.
     */
    function bindEndOpensAtStart(fromEl, toEl) {
        if (!fromEl || !toEl) return;

        function syncMin() {
            var from = val(fromEl);
            if (from) {
                toEl.min = from;
            } else {
                toEl.removeAttribute('min');
            }
        }

        function seedMonth() {
            syncMin();
            var from = val(fromEl);
            if (!from) return;
            var current = val(toEl);
            if (toEl.dataset.pickerRestore === undefined) {
                toEl.dataset.pickerRestore = current;
            }
            if (!current || current.slice(0, 7) !== from.slice(0, 7)) {
                toEl.value = from;
            }
        }

        function clearRestore() {
            delete toEl.dataset.pickerRestore;
        }

        function restoreIfCancelled() {
            if (toEl.dataset.pickerRestore === undefined) return;
            var restore = toEl.dataset.pickerRestore;
            var from = val(fromEl);
            var current = val(toEl);
            if (current === from && restore !== current) {
                toEl.value = restore;
            }
            clearRestore();
        }

        toEl.addEventListener('pointerdown', seedMonth);
        toEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                seedMonth();
            }
        });
        toEl.addEventListener('change', clearRestore);
        toEl.addEventListener('blur', restoreIfCancelled);
        fromEl.addEventListener('change', syncMin);
        fromEl.addEventListener('input', syncMin);
        syncMin();
    }

    /** Fyll tomme (eller tidligere foreslåtte) datoer ut fra sesong / forrige runde. */
    function suggestDates(form) {
        var seasonFrom = val(form.querySelector('.js-season-from'));
        var seasonTo = val(form.querySelector('.js-season-to'));
        var trs = Array.prototype.slice.call(form.querySelectorAll('tbody tr.js-round-data-row'));
        if (!trs.length) return;

        var fromInputs = trs.map(function (tr) { return tr.querySelector('.js-round-from'); });
        var toInputs = trs.map(function (tr) { return tr.querySelector('.js-round-to'); });

        setSuggested(fromInputs[0], seasonFrom);
        setSuggested(toInputs[toInputs.length - 1], seasonTo);

        for (var i = 0; i < toInputs.length - 1; i++) {
            var prevEnd = val(toInputs[i]);
            if (prevEnd) {
                setSuggested(fromInputs[i + 1], addDays(prevEnd, 1));
            } else {
                clearSuggested(fromInputs[i + 1]);
            }
        }
    }

    function checkForm(form) {
        var seasonFrom = val(form.querySelector('.js-season-from'));
        var seasonTo = val(form.querySelector('.js-season-to'));
        var seasonRow = form.querySelector('.season-period-row');
        var seasonError = form.querySelector('.js-season-error');
        var dataRows = Array.prototype.slice.call(form.querySelectorAll('tbody tr.js-round-data-row'));
        var activeRow = form._activeRoundRow || null;

        var seasonMsgs = [];
        if (seasonFrom && seasonTo && seasonTo < seasonFrom) {
            seasonMsgs.push('Til-dato kan ikke være før fra-dato.');
        }
        if (seasonRow) {
            seasonRow.classList.toggle('has-error', seasonMsgs.length > 0);
        }
        setErrorBox(seasonError, seasonMsgs);

        var rowMsgs = dataRows.map(function () { return []; });
        var dated = [];

        dataRows.forEach(function (tr, idx) {
            var from = val(tr.querySelector('.js-round-from'));
            var to = val(tr.querySelector('.js-round-to'));
            var name = val(tr.querySelector('input[name*="[name]"]')) || ('Runde ' + (idx + 1));

            if (from && to && to < from) {
                rowMsgs[idx].push('Til-dato kan ikke være før fra-dato.');
            }
            if (from && seasonFrom && from < seasonFrom) {
                rowMsgs[idx].push('Fra-dato er før sesongens start.');
            }
            if (from && seasonTo && from > seasonTo) {
                rowMsgs[idx].push('Fra-dato er etter sesongens slutt.');
            }
            if (to && seasonFrom && to < seasonFrom) {
                rowMsgs[idx].push('Til-dato er før sesongens start.');
            }
            if (to && seasonTo && to > seasonTo) {
                rowMsgs[idx].push('Til-dato er etter sesongens slutt.');
            }
            if (from && to) {
                dated.push({ idx: idx, name: name, from: from, to: to });
            }
        });

        dated.sort(function (a, b) {
            return a.from < b.from ? -1 : (a.from > b.from ? 1 : a.idx - b.idx);
        });
        for (var i = 0; i < dated.length - 1; i++) {
            if (dated[i].to >= dated[i + 1].from) {
                rowMsgs[dated[i].idx].push('Overlapp med ' + dated[i + 1].name + '.');
                rowMsgs[dated[i + 1].idx].push('Overlapp med ' + dated[i].name + '.');
            }
        }

        var activeIdx = activeRow ? dataRows.indexOf(activeRow) : -1;

        dataRows.forEach(function (tr, idx) {
            var errRow = tr.nextElementSibling;
            var errBox = errRow && errRow.classList.contains('js-round-error-row')
                ? errRow.querySelector('.js-round-error')
                : null;
            var msgs = rowMsgs[idx];
            var hasErr = msgs.length > 0;
            // Vis tekst ved aktiv runde; uten aktiv runde: ved alle med feil.
            var showMsg = hasErr && (activeIdx < 0 || idx === activeIdx);

            tr.classList.toggle('has-error', hasErr);
            if (errRow && errRow.classList.contains('js-round-error-row')) {
                if (showMsg) {
                    errRow.classList.add('is-visible');
                    errRow.setAttribute('aria-hidden', 'false');
                    setErrorBox(errBox, msgs);
                } else {
                    errRow.classList.remove('is-visible');
                    errRow.setAttribute('aria-hidden', 'true');
                    setErrorBox(errBox, []);
                }
            }
        });
    }

    function refresh(form) {
        suggestDates(form);
        checkForm(form);
    }

    document.querySelectorAll('.rounds-matrix-form').forEach(function (form) {
        form.querySelectorAll('.js-round-from, .js-round-to, .js-season-from, .js-season-to').forEach(function (el) {
            el.addEventListener('input', function () { markManualIfChanged(el); });
            el.addEventListener('change', function () { markManualIfChanged(el); });
        });
        bindEndOpensAtStart(form.querySelector('.js-season-from'), form.querySelector('.js-season-to'));
        form.querySelectorAll('tbody tr.js-round-data-row').forEach(function (tr) {
            bindEndOpensAtStart(tr.querySelector('.js-round-from'), tr.querySelector('.js-round-to'));
            tr.addEventListener('focusin', function () { form._activeRoundRow = tr; });
            tr.querySelectorAll('input').forEach(function (inp) {
                inp.addEventListener('input', function () { form._activeRoundRow = tr; });
                inp.addEventListener('change', function () { form._activeRoundRow = tr; });
            });
        });
        form.querySelectorAll('.js-season-from, .js-season-to').forEach(function (el) {
            el.addEventListener('focusin', function () { form._activeRoundRow = null; });
        });
        form.addEventListener('change', function () { refresh(form); });
        form.addEventListener('input', function () { refresh(form); });
        refresh(form);
    });
})();
</script>
