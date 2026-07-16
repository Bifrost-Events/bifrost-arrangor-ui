<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $space */
/** @var array<string, mixed> $event */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var array<string, mixed> $grid */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$eventId = (int) ($event['event_id'] ?? 0);
$slots = is_array($grid['slots'] ?? null) ? $grid['slots'] : [];
$maxPos = 0;
foreach ($slots as $slot) {
    $maxPos = max($maxPos, count($slot['positions'] ?? []));
}
?>
<p><a href="<?= $h($pp::stevne($eventId)) ?>">← <?= $h($labels->singular('event')) ?></a>
 · <a href="<?= $h($pp::stevnePameldinger($eventId)) ?>">Påmeldinger</a></p>
<h1>Jaktfelt-grid</h1>
<p class="muted"><?= $h((string) ($event['name'] ?? '')) ?></p>

<div class="card" style="margin-bottom:1rem; max-width:36rem;">
    <h2 style="margin-top:0; font-size:1.05rem;">Generer / oppdater grid</h2>
    <form method="post" action="<?= $h($pp::stevneJaktfelt($eventId)) ?>" style="display:grid; gap:.5rem;">
        <label>Antall lag <input type="number" name="slot_count" min="1" value="4" required></label>
        <label>Plasser per lag <input type="number" name="positions_per_slot" min="1" value="6" required></label>
        <label>Reserverte lag <input type="number" name="reserved_slots" min="0" value="0"></label>
        <label>Første start <input type="datetime-local" name="first_starts_at"></label>
        <label>Minutter mellom lag <input type="number" name="minutes_between_slots" min="0" value="60"></label>
        <button type="submit" class="btn">Generer</button>
    </form>
    <p class="muted" style="margin-top:.5rem;">Aktiverer jaktfelt-modul på arrangementet og synker max_participants.</p>
</div>

<?php if ($slots === []): ?>
    <p class="muted">Ingen grid ennå.</p>
<?php else: ?>
    <table class="table" style="width:100%; border-collapse:collapse; font-size:.9rem;">
        <thead>
            <tr>
                <th style="text-align:left; padding:.35rem;">Lag</th>
                <th style="text-align:left; padding:.35rem;">Start</th>
                <?php for ($i = 1; $i <= $maxPos; $i++): ?>
                    <th style="text-align:left; padding:.35rem;">Plass <?= $i ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($slots as $slot): ?>
                <tr>
                    <td style="padding:.35rem;"><?= (int) ($slot['slot_number'] ?? 0) ?><?= !empty($slot['is_reserved']) ? ' (res.)' : '' ?><?= !empty($slot['is_locked']) ? ' 🔒' : '' ?></td>
                    <td style="padding:.35rem;"><?= $h((string) ($slot['starts_at'] ?? '—')) ?></td>
                    <?php
                    $byNum = [];
                    foreach (($slot['positions'] ?? []) as $pos) {
                        $byNum[(int) ($pos['position_number'] ?? 0)] = $pos;
                    }
                    for ($i = 1; $i <= $maxPos; $i++):
                        $pos = $byNum[$i] ?? null;
                        if ($pos === null) {
                            echo '<td style="padding:.35rem;">—</td>';
                            continue;
                        }
                        $label = 'Ledig';
                        if (!empty($pos['is_reserved'])) {
                            $label = 'Reservert';
                        }
                        if (($pos['registration_id'] ?? null) !== null) {
                            $label = 'Påmeldt #' . (int) $pos['registration_id'];
                        }
                        if (!empty($pos['is_locked'])) {
                            $label .= ' 🔒';
                        }
                        echo '<td style="padding:.35rem;">' . $h($label) . '</td>';
                    endfor;
                    ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="card" style="margin-top:1rem; max-width:36rem;">
        <h2 style="margin-top:0; font-size:1.05rem;">Flytt deltaker</h2>
        <form method="post" action="<?= $h($pp::stevneJaktfelt($eventId)) ?>/flytt" style="display:grid; gap:.5rem;">
            <label>Registration-ID <input type="number" name="registration_id" required min="1"></label>
            <label>Mål plass-ID <input type="number" name="target_slot_position_id" required min="1"></label>
            <button type="submit" class="btn secondary">Flytt</button>
        </form>
    </div>

    <div class="card" style="margin-top:1rem; max-width:36rem;">
        <h2 style="margin-top:0; font-size:1.05rem;">Manuell påmelding</h2>
        <form method="post" action="<?= $h($pp::stevneJaktfelt($eventId)) ?>/pamelding" style="display:grid; gap:.5rem;">
            <label>Person-ID <input type="number" name="person_id" required min="1"></label>
            <label>Plass-ID <input type="number" name="slot_position_id" required min="1"></label>
            <label>Klasse <input type="text" name="class_name" required></label>
            <label>Klasse-nøkkel <input type="text" name="class_key"></label>
            <button type="submit" class="btn">Meld på</button>
        </form>
    </div>
<?php endif; ?>
