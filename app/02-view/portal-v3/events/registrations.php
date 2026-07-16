<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $space */
/** @var array<string, mixed> $event */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var array<string, mixed> $payload */
/** @var array{registration_status: string, attendance_status: string, q: string} $filters */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$eventId = (int) ($event['event_id'] ?? 0);
$eventName = (string) ($event['name'] ?? '');
$summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
$regs = is_array($payload['registrations'] ?? null) ? $payload['registrations'] : [];
$pagination = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];
$statusCounts = is_array($summary['status_counts'] ?? null) ? $summary['status_counts'] : [];
$eventLabel = $labels->singular('event');
?>
<p class="muted" style="margin:0 0 .35rem;"><?= $h($eventName) ?></p>
<p>
    <a href="<?= $h($pp::stevne($eventId)) ?>">← <?= $h($eventLabel) ?></a>
</p>
<h1>Påmeldinger</h1>

<div class="card" style="margin-bottom:1rem;">
    <p style="margin:0;">
        Aktive: <strong><?= (int) ($summary['active_count'] ?? 0) ?></strong>
        <?php if (($summary['max_participants'] ?? null) !== null): ?>
            / <?= (int) $summary['max_participants'] ?>
            (<?= (int) ($summary['remaining_slots'] ?? 0) ?> ledige)
        <?php endif; ?>
    </p>
    <?php if ($statusCounts !== []): ?>
        <p class="muted" style="margin:.5rem 0 0;">
            <?php foreach ($statusCounts as $st => $cnt): ?>
                <?= $h((string) $st) ?>: <?= (int) $cnt ?><?= $st !== array_key_last($statusCounts) ? ' · ' : '' ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <p style="margin:.75rem 0 0;">
        <a class="btn" href="<?= $h($pp::stevnePameldingNy($eventId)) ?>">Manuell registrering</a>
        <a class="btn secondary" href="<?= $h($pp::stevnePameldingerExport($eventId)) ?>">Eksporter CSV</a>
    </p>
</div>

<form method="get" action="<?= $h($pp::stevnePameldinger($eventId)) ?>" class="card" style="margin-bottom:1rem; display:grid; gap:.75rem; max-width:40rem;">
    <label for="q">Søk (navn)</label>
    <input type="text" id="q" name="q" value="<?= $h($filters['q']) ?>">

    <label for="registration_status">Status</label>
    <select id="registration_status" name="registration_status">
        <option value="">Alle</option>
        <?php foreach (['pending', 'confirmed', 'cancelled', 'rejected', 'waitlisted'] as $st): ?>
            <option value="<?= $st ?>" <?= $filters['registration_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
    </select>

    <label for="attendance_status">Oppmøte</label>
    <select id="attendance_status" name="attendance_status">
        <option value="">Alle</option>
        <?php foreach (['attended', 'no_show', 'withdrawn'] as $st): ?>
            <option value="<?= $st ?>" <?= $filters['attendance_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
    </select>

    <p><button type="submit" class="btn secondary">Filtrer</button></p>
</form>

<?php if ($regs === []): ?>
    <p class="muted">Ingen påmeldinger funnet.</p>
<?php else: ?>
    <table class="table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left; padding:.4rem;">Person</th>
                <th style="text-align:left; padding:.4rem;">Status</th>
                <th style="text-align:left; padding:.4rem;">Oppmøte</th>
                <th style="text-align:left; padding:.4rem;">Kilde</th>
                <th style="text-align:left; padding:.4rem;">Registrert</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($regs as $row): ?>
                <?php
                $rid = (int) ($row['registration_id'] ?? 0);
                ?>
                <tr>
                    <td style="padding:.4rem;"><?= $h((string) ($row['person_display_name'] ?? '')) ?></td>
                    <td style="padding:.4rem;"><?= $h((string) ($row['registration_status'] ?? '')) ?></td>
                    <td style="padding:.4rem;"><?= $h((string) ($row['attendance_status'] ?? '—')) ?></td>
                    <td style="padding:.4rem;"><?= $h((string) ($row['source'] ?? '')) ?></td>
                    <td style="padding:.4rem;"><?= $h((string) ($row['registered_at'] ?? '')) ?></td>
                    <td style="padding:.4rem;"><a href="<?= $h($pp::stevnePamelding($eventId, $rid)) ?>">Åpne</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ((int) ($pagination['total_pages'] ?? 1) > 1): ?>
        <p class="muted">Side <?= (int) ($pagination['page'] ?? 1) ?> av <?= (int) ($pagination['total_pages'] ?? 1) ?> (<?= (int) ($pagination['total'] ?? 0) ?> totalt)</p>
    <?php endif; ?>
<?php endif; ?>
