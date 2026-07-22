<?php

declare(strict_types=1);

/** @var array<string, mixed> $application */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$statusLabels = [
    'draft' => 'Utkast',
    'submitted' => 'Sendt inn',
    'under_review' => 'Under behandling',
    'approved' => 'Godkjent',
    'rejected' => 'Avvist',
    'withdrawn' => 'Trukket',
];
$id = (int) ($application['organizer_application_id'] ?? 0);
$status = (string) ($application['application_status'] ?? '');
$label = $statusLabels[$status] ?? $status;
?>
<p><a href="<?= $h($pp::arrangorSoknader()) ?>">← Arrangørsøknader</a></p>
<h1>Søknad #<?= $id ?></h1>

<div class="card">
    <p><strong>Status:</strong> <?= $h($label) ?></p>
    <p><strong>Organisasjon:</strong> <?= $h((string) ($application['org_name'] ?? $application['organization_name'] ?? '')) ?></p>
    <p><strong>Sesong:</strong> <?= $h((string) ($application['series_name'] ?? '')) ?></p>
    <?php if ($status === 'approved'): ?>
        <p class="muted">Organisasjonen er godkjent som arrangør for sesongen og kan opprette stevner fritt.</p>
        <p style="margin-top:1rem;">
            <a class="btn" href="<?= $h($pp::stevner() . '?season_scope=all') ?>">Gå til mine stevner</a>
        </p>
    <?php else: ?>
        <p class="muted">Søknaden gjelder hele sesongen (ikke et enkelt stevne).</p>
    <?php endif; ?>
    <?php if (!empty($application['message'])): ?>
        <p><strong>Melding:</strong> <?= nl2br($h((string) $application['message'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($application['review_notes'])): ?>
        <p><strong>Merknad fra serieeier:</strong> <?= nl2br($h((string) $application['review_notes'])) ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($status === 'draft'): ?>
        <form method="post" action="<?= $h($pp::arrangorSoknad($id) . '/send-inn') ?>" style="display:inline;">
            <button type="submit" class="btn">Send inn</button>
        </form>
    <?php endif; ?>
    <?php if (in_array($status, ['draft', 'submitted', 'under_review'], true)): ?>
        <form method="post" action="<?= $h($pp::arrangorSoknad($id) . '/trekk') ?>" style="display:inline;margin-left:.35rem;"
              onsubmit="return confirm('Trekke søknaden?');">
            <button type="submit" class="btn">Trekk søknad</button>
        </form>
    <?php endif; ?>
</div>
