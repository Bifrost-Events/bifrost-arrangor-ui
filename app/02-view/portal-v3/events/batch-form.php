<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed> $season */
/** @var list<array<string, mixed>> $rows */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var bool $show_force_outside */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$cupName = (string) ($space['name'] ?? '');
$seasonLabel = trim((string) ($season['name'] ?? $season['season_label'] ?? 'Sesong'));
$seasonId = (int) ($season['series_id'] ?? 0);
$rows = is_array($rows ?? null) ? $rows : [];
$showForceOutside = (bool) ($show_force_outside ?? false);
$backHref = $pp::stevner() . '?season_scope=all';

$formatNbDate = static function (?string $ymd): string {
    if ($ymd === null || $ymd === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1];
    }

    return $ymd;
};
?>
<p class="muted" style="margin:0 0 .35rem;">
    <?php if ($cupName !== ''): ?><?= $h($cupName) ?> › <?php endif; ?>
    <?= $h($seasonLabel) ?>
</p>
<p><a href="<?= $h($backHref) ?>">← <?= $h($labels->plural('event')) ?></a></p>

<h1>Opprett stevner</h1>
<p class="muted">Fyll inn radene du vil opprette. Tomt navn hopper over runden. Stevnedato bør ligge innenfor rundens intervall.</p>

<style>
    .batch-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: .75rem;
    }
    .batch-table th,
    .batch-table td {
        text-align: left;
        padding: .55rem .45rem;
        vertical-align: top;
        border-bottom: 1px solid #e6e8e4;
    }
    .batch-table th {
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--muted, #5c635c);
    }
    .batch-table input[type="text"],
    .batch-table input[type="date"],
    .batch-table input[type="time"] {
        width: 100%;
        max-width: 16rem;
        box-sizing: border-box;
    }
    .batch-datetime {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        align-items: center;
    }
    .batch-datetime input[type="date"] { max-width: 10.5rem; }
    .batch-datetime input[type="time"] { max-width: 7.5rem; }
    .batch-round {
        font-weight: 650;
        white-space: nowrap;
        padding-top: .85rem !important;
    }
    .batch-round-meta {
        display: block;
        font-weight: 500;
        font-size: .8rem;
        color: var(--muted, #5c635c);
        margin-top: .2rem;
    }
    .batch-date-warn {
        display: none;
        margin-top: .35rem;
        font-size: .82rem;
        color: #8a5a00;
        background: #fff6e5;
        border: 1px solid #f0d9a8;
        border-radius: 4px;
        padding: .35rem .5rem;
        max-width: 18rem;
    }
    .batch-date-warn.is-visible { display: block; }
    .batch-actions {
        margin-top: 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: center;
    }
    .batch-force {
        margin-top: 1rem;
        padding: .75rem .9rem;
        background: #fff6e5;
        border: 1px solid #f0d9a8;
        border-radius: 6px;
    }
</style>

<div class="card">
    <form method="post" action="<?= $h($pp::sesongStevnerBatch($seasonId)) ?>" id="batch-stevner-form">
        <table class="batch-table">
            <thead>
                <tr>
                    <th>Runde</th>
                    <th>Navn</th>
                    <th>Sted</th>
                    <th>Dato</th>
                    <th>Tid</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $rid = (int) ($row['series_id'] ?? 0);
                $from = isset($row['round_starts_on']) ? (string) $row['round_starts_on'] : '';
                $to = isset($row['round_ends_on']) ? (string) $row['round_ends_on'] : '';
                $periodParts = [];
                if ($from !== '') {
                    $periodParts[] = 'fra ' . $formatNbDate($from);
                }
                if ($to !== '') {
                    $periodParts[] = 'til ' . $formatNbDate($to);
                }
                $periodLabel = $periodParts !== [] ? implode(' ', $periodParts) : '';
                $hasWarning = trim((string) ($row['date_warning'] ?? '')) !== '';
                $startDate = (string) ($row['start_date'] ?? '');
                $startTime = (string) ($row['start_time'] ?? '10:00');
                if (strlen($startTime) > 5) {
                    $startTime = substr($startTime, 0, 5);
                }
                ?>
                <tr>
                    <td class="batch-round">
                        <?= $h((string) ($row['round_label'] ?? '')) ?>
                        <?php if ($periodLabel !== ''): ?>
                            <span class="batch-round-meta"><?= $h($periodLabel) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="text"
                               name="rounds[<?= $rid ?>][name]"
                               value="<?= $h((string) ($row['name'] ?? '')) ?>"
                               aria-label="Navn for <?= $h((string) ($row['round_label'] ?? 'runde')) ?>">
                    </td>
                    <td>
                        <input type="text"
                               name="rounds[<?= $rid ?>][location_name]"
                               value="<?= $h((string) ($row['location_name'] ?? '')) ?>"
                               aria-label="Sted for <?= $h((string) ($row['round_label'] ?? 'runde')) ?>">
                    </td>
                    <td>
                        <input type="date"
                               class="batch-start-date"
                               name="rounds[<?= $rid ?>][start_date]"
                               value="<?= $h($startDate) ?>"
                               data-round-from="<?= $h($from) ?>"
                               data-round-to="<?= $h($to) ?>"
                               data-warn-id="warn-<?= $rid ?>"
                               aria-label="Dato for <?= $h((string) ($row['round_label'] ?? 'runde')) ?>">
                        <div id="warn-<?= $rid ?>"
                             class="batch-date-warn<?= $hasWarning ? ' is-visible' : '' ?>"
                             role="status">
                            <?= $hasWarning
                                ? $h((string) $row['date_warning'])
                                : 'Stevnedato er utenfor rundens intervall.' ?>
                        </div>
                    </td>
                    <td>
                        <input type="time"
                               name="rounds[<?= $rid ?>][start_time]"
                               value="<?= $h($startTime !== '' ? $startTime : '10:00') ?>"
                               aria-label="Tid for <?= $h((string) ($row['round_label'] ?? 'runde')) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($showForceOutside): ?>
            <div class="batch-force">
                <label>
                    <input type="checkbox" name="force_outside_dates" value="1">
                    Lagre likevel selv om dato er utenfor rundens intervall
                </label>
            </div>
        <?php endif; ?>

        <div class="batch-actions">
            <button type="submit" class="btn">Lagre stevner</button>
            <a class="btn secondary" href="<?= $h($backHref) ?>">Avbryt</a>
        </div>
    </form>
</div>
<script>
(function () {
    function checkDateInput(input) {
        var warn = document.getElementById(input.getAttribute('data-warn-id') || '');
        if (!warn) return;
        var value = (input.value || '').trim();
        var from = (input.getAttribute('data-round-from') || '').trim();
        var to = (input.getAttribute('data-round-to') || '').trim();
        if (!value || (!from && !to)) {
            if (!warn.getAttribute('data-server')) {
                warn.classList.remove('is-visible');
            }
            return;
        }
        var outside = (from && value < from) || (to && value > to);
        warn.classList.toggle('is-visible', outside);
    }

    /** Åpne kalender i rundens startmåned (native date åpner ellers ofte på i dag). */
    function bindOpenAtRoundStart(input) {
        var from = (input.getAttribute('data-round-from') || '').trim();
        var to = (input.getAttribute('data-round-to') || '').trim();
        if (from) {
            input.min = from;
        }
        if (to) {
            input.max = to;
        }

        function seedMonth() {
            var roundFrom = (input.getAttribute('data-round-from') || '').trim();
            if (!roundFrom) return;
            var current = (input.value || '').trim();
            if (input.dataset.pickerRestore === undefined) {
                input.dataset.pickerRestore = current;
            }
            if (!current || current.slice(0, 7) !== roundFrom.slice(0, 7)) {
                input.value = roundFrom;
            }
        }

        function clearRestore() {
            delete input.dataset.pickerRestore;
        }

        function restoreIfCancelled() {
            if (input.dataset.pickerRestore === undefined) return;
            var restore = input.dataset.pickerRestore;
            var roundFrom = (input.getAttribute('data-round-from') || '').trim();
            var current = (input.value || '').trim();
            if (current === roundFrom && restore !== current) {
                input.value = restore;
            }
            clearRestore();
            checkDateInput(input);
        }

        input.addEventListener('pointerdown', seedMonth);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                seedMonth();
            }
        });
        input.addEventListener('change', clearRestore);
        input.addEventListener('blur', restoreIfCancelled);
    }

    document.querySelectorAll('.batch-start-date').forEach(function (input) {
        var warn = document.getElementById(input.getAttribute('data-warn-id') || '');
        if (warn && warn.classList.contains('is-visible')) {
            warn.setAttribute('data-server', '1');
        }
        bindOpenAtRoundStart(input);
        input.addEventListener('change', function () {
            if (warn) warn.removeAttribute('data-server');
            checkDateInput(input);
        });
        checkDateInput(input);
    });
})();
</script>
