<?php

declare(strict_types=1);

/** @var int $series_id */
/** @var array<string, mixed>|null $series */
/** @var list<array<string, mixed>> $applications */
/** @var string $filter_status */
/** @var string $onboarding_mode */

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
$modeLabels = [
    'closed' => 'Stengt',
    'open' => 'Åpen (auto-godkjenn)',
    'approval_required' => 'Krever godkjenning',
    'invite_only' => 'Kun invitasjon',
];
$seriesName = (string) ($series['name'] ?? $series['season_label'] ?? 'Serie');
$currentMode = (string) ($onboarding_mode ?? 'closed');
?>
<h1>Søknader om stevne</h1>
<p class="muted"><?= $h($seriesName) ?></p>

<div class="card" style="max-width:36rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Åpne for arrangørsøknader</h2>
    <form method="post" action="<?= $h($pp::serieSoknadInnstillinger($series_id)) ?>" id="onboarding-settings-form">
        <label for="onboarding_mode">Modus</label>
        <select id="onboarding_mode" name="mode">
            <?php foreach ($modeLabels as $value => $label): ?>
                <option value="<?= $h($value) ?>" <?= $currentMode === $value ? 'selected' : '' ?>>
                    <?= $h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="margin-top:1rem;">
            <button type="submit" class="btn" id="onboarding-settings-save">Lagre innstillinger</button>
        </p>
    </form>
</div>

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
                <a href="<?= $h($pp::serieSoknad($series_id, $id)) ?>">
                    <?= $h((string) ($app['org_name'] ?? $app['organization_name'] ?? 'Organisasjon')) ?>
                </a>
            </h2>
            <p class="muted" style="margin:0;"><?= $h($label) ?></p>
            <?php if (!empty($app['message'])): ?>
                <p style="margin:.35rem 0 0;" class="muted"><?= $h(mb_strimwidth((string) $app['message'], 0, 120, '…')) ?></p>
            <?php endif; ?>
            <p style="margin-top:.75rem;">
                <a class="btn" href="<?= $h($pp::serieSoknad($series_id, $id)) ?>">Behandle</a>
            </p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
