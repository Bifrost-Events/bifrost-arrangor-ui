<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $space */
/** @var array<string, mixed> $event */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var array<string, mixed> $registration */
/** @var array<string, mixed> $allowed */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$eventId = (int) ($event['event_id'] ?? 0);
$regId = (int) ($registration['registration_id'] ?? 0);
$currentStatus = (string) ($registration['registration_status'] ?? '');
$regOptions = is_array($allowed['registration_status'] ?? null) ? $allowed['registration_status'] : [];
$attOptions = is_array($allowed['attendance_status'] ?? null) ? $allowed['attendance_status'] : [];
$reactivationRequired = !empty($allowed['reactivation_required_from']);
?>
<p>
    <a href="<?= $h($pp::stevnePameldinger($eventId)) ?>">← Påmeldinger</a>
</p>
<h1>Påmelding #<?= $regId ?></h1>

<div class="card" style="max-width:40rem; margin-bottom:1rem;">
    <p><strong>Person:</strong> <?= $h((string) ($registration['person_display_name'] ?? '')) ?></p>
    <?php if (!empty($registration['person_email'])): ?>
        <p><strong>E-post:</strong> <?= $h((string) $registration['person_email']) ?></p>
    <?php endif; ?>
    <?php if (!empty($registration['person_phone'])): ?>
        <p><strong>Telefon:</strong> <?= $h((string) $registration['person_phone']) ?></p>
    <?php endif; ?>
    <p><strong>Meldt på av:</strong>
        <?= $h((string) ($registration['registered_by_display_name'] ?? ('bruker #' . (int) ($registration['registered_by_user_id'] ?? 0)))) ?>
    </p>
    <p><strong>Tidspunkt:</strong> <?= $h((string) ($registration['registered_at'] ?? '')) ?></p>
    <p><strong>Kilde:</strong> <?= $h((string) ($registration['source'] ?? '')) ?></p>
    <p><strong>Status:</strong> <?= $h($currentStatus) ?></p>
    <p><strong>Oppmøte:</strong> <?= $h((string) ($registration['attendance_status'] ?? '—')) ?></p>
</div>

<form method="post" action="<?= $h($pp::stevnePamelding($eventId, $regId)) ?>" class="card" style="max-width:40rem; display:grid; gap:.75rem;">
    <label for="registration_status">Ny registreringsstatus</label>
    <select id="registration_status" name="registration_status">
        <option value="">— behold <?= $h($currentStatus) ?> —</option>
        <?php foreach ($regOptions as $st): ?>
            <option value="<?= $h((string) $st) ?>"><?= $h((string) $st) ?></option>
        <?php endforeach; ?>
    </select>

    <?php if ($reactivationRequired): ?>
        <label><input type="checkbox" name="reactivate" value="1"> Bekreft reaktivering/gjenåpning</label>
        <label><input type="checkbox" name="force_capacity_override" value="1"> Overstyr kapasitet ved behov</label>
    <?php endif; ?>

    <label for="attendance_status">Oppmøte</label>
    <select id="attendance_status" name="attendance_status">
        <?php foreach ($attOptions as $st): ?>
            <?php $val = $st === null ? '' : (string) $st; ?>
            <option value="<?= $h($val) ?>" <?= (($registration['attendance_status'] ?? null) === $st || (($registration['attendance_status'] ?? null) === null && $val === '')) ? 'selected' : '' ?>>
                <?= $val === '' ? '— ingen —' : $h($val) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="notes">Notater</label>
    <textarea id="notes" name="notes" rows="3"><?= $h((string) ($registration['notes'] ?? '')) ?></textarea>

    <p><button type="submit" class="btn">Lagre endringer</button></p>
</form>
