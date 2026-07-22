<?php

declare(strict_types=1);

/** @var int $series_id */
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
$base = $pp::serieSoknad($series_id, $id);
?>
<p><a href="<?= $h($pp::serieSoknader($series_id)) ?>">← Søknader</a></p>
<h1>Behandle søknad #<?= $id ?></h1>

<div class="card">
    <p><strong>Status:</strong> <?= $h($label) ?></p>
    <p><strong>Organisasjon:</strong> <?= $h((string) ($application['org_name'] ?? $application['organization_name'] ?? '')) ?></p>
    <p><strong>Sesong:</strong> <?= $h((string) ($application['series_name'] ?? '')) ?></p>
    <p class="muted">Søknaden gjelder hele sesongen. Ved godkjenning blir organisasjonen arrangør og kan opprette stevner fritt.</p>
    <?php if (!empty($application['message'])): ?>
        <p><strong>Melding:</strong> <?= nl2br($h((string) $application['message'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($application['review_notes'])): ?>
        <p><strong>Merknad:</strong> <?= nl2br($h((string) $application['review_notes'])) ?></p>
    <?php endif; ?>
</div>

<?php if (in_array($status, ['submitted', 'under_review'], true)): ?>
<div class="card" style="max-width:36rem;">
    <?php if ($status === 'submitted'): ?>
        <form method="post" action="<?= $h($base . '/under-behandling') ?>" style="margin-bottom:1rem;">
            <label for="review_notes_ur">Merknad (valgfritt)</label>
            <textarea id="review_notes_ur" name="review_notes" rows="2"></textarea>
            <p style="margin-top:.75rem;"><button type="submit" class="btn">Sett under behandling</button></p>
        </form>
    <?php endif; ?>

    <form method="post" action="<?= $h($base . '/godkjenn') ?>" style="margin-bottom:1rem;">
        <label for="review_notes_ok">Merknad ved godkjenning</label>
        <textarea id="review_notes_ok" name="review_notes" rows="2"></textarea>
        <p style="margin-top:.75rem;"><button type="submit" class="btn">Godkjenn som sesongarrangør</button></p>
    </form>

    <form method="post" action="<?= $h($base . '/avvis') ?>">
        <label for="review_notes_no">Begrunnelse ved avvisning *</label>
        <textarea id="review_notes_no" name="review_notes" rows="2" required></textarea>
        <p style="margin-top:.75rem;"><button type="submit" class="btn">Avvis</button></p>
    </form>
</div>
<?php endif; ?>
