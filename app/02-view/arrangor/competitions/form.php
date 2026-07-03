<?php

declare(strict_types=1);

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
<h2 style="margin-top:0;"><?= $isEdit ? 'Rediger stevne' : 'Nytt stevne' ?></h2>
<p class="lead">Stevnet knyttes til valgt cup, sesong og runde.</p>

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
<form method="post" action="<?= $h($action) ?>" class="form-grid">
    <div>
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
    <div>
        <label for="name">Stevnenavn *</label>
        <input type="text" id="name" name="name" value="<?= $h((string) ($form['name'] ?? '')) ?>" required>
    </div>
    <div>
        <label for="event_date">Dato</label>
        <input type="date" id="event_date" name="event_date" value="<?= $h((string) ($form['event_date'] ?? '')) ?>">
    </div>
    <div>
        <label for="location">Sted</label>
        <input type="text" id="location" name="location" value="<?= $h((string) ($form['location'] ?? '')) ?>">
    </div>
    <div>
        <label for="description">Beskrivelse</label>
        <textarea id="description" name="description" rows="4"><?= $h((string) ($form['description'] ?? '')) ?></textarea>
    </div>
    <div class="toolbar">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lagre' : 'Opprett' ?></button>
        <a class="btn" href="/stevner">Avbryt</a>
    </div>
</form>
<script>
(function() {
    var roundSelect = document.getElementById('round_id');
    var dateInput = document.getElementById('event_date');
    if (!roundSelect || !dateInput) {
        return;
    }

    function selectedRoundDates() {
        var opt = roundSelect.options[roundSelect.selectedIndex];
        if (!opt || !opt.value) {
            return { start: '', end: '' };
        }
        return {
            start: opt.getAttribute('data-start-date') || '',
            end: opt.getAttribute('data-end-date') || ''
        };
    }

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

    function primeDatePickerMonth() {
        if (dateInput.value) {
            return;
        }
        var start = selectedRoundDates().start;
        if (!start) {
            return;
        }
        dateInput.dataset.pickerProbe = start;
        dateInput.value = start;
    }

    syncDateBounds();
    roundSelect.addEventListener('change', syncDateBounds);

    if (!dateInput.dataset.pickerBound) {
        dateInput.dataset.pickerBound = '1';
        dateInput.addEventListener('focus', primeDatePickerMonth);
        dateInput.addEventListener('click', primeDatePickerMonth);
        dateInput.addEventListener('change', function() {
            dateInput.dataset.pickerCommitted = '1';
            delete dateInput.dataset.pickerProbe;
        });
        dateInput.addEventListener('blur', function() {
            if (dateInput.dataset.pickerCommitted === '1') {
                delete dateInput.dataset.pickerCommitted;
                return;
            }
            if (dateInput.dataset.pickerProbe && dateInput.value === dateInput.dataset.pickerProbe) {
                dateInput.value = '';
            }
            delete dateInput.dataset.pickerProbe;
        });
    }
})();
</script>
<?php endif; ?>
