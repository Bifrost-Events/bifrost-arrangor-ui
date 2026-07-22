<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $applications */
/** @var string $filter_status */

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
?>
<h1>Arrangørsøknader</h1>
<p class="muted">Søknader fra organisasjoner du administrerer.</p>
<p><a class="btn" href="<?= $h($pp::arrangorSoknadNy()) ?>">Ny søknad</a></p>

<?php if ($applications === []): ?>
    <div class="card">
        <p>Ingen søknader<?= $filter_status !== '' ? ' med valgt status' : '' ?>.</p>
    </div>
<?php else: ?>
    <?php foreach ($applications as $app): ?>
        <?php
        $id = (int) ($app['organizer_application_id'] ?? 0);
        $status = (string) ($app['application_status'] ?? '');
        $label = $statusLabels[$status] ?? $status;
        ?>
        <div class="card">
            <h2 style="margin:0 0 .35rem;font-size:1.05rem;">
                <a href="<?= $h($pp::arrangorSoknad($id)) ?>">
                    <?= $h((string) ($app['org_name'] ?? $app['organization_name'] ?? 'Organisasjon')) ?>
                </a>
            </h2>
            <p class="muted" style="margin:0;">
                <?= $h((string) ($app['series_name'] ?? 'Serie')) ?>
                · <?= $h($label) ?>
            </p>
            <p style="margin-top:.75rem;">
                <a class="btn" href="<?= $h($pp::arrangorSoknad($id)) ?>">Åpne</a>
                <?php if ($status === 'approved'): ?>
                    <a class="btn secondary" href="<?= $h($pp::stevner() . '?season_scope=all') ?>">Mine stevner</a>
                <?php endif; ?>
            </p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
