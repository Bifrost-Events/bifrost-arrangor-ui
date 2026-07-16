<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed>|null $season */
/** @var array<string, mixed> $arranger */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var string $route_prefix */
/** @var bool $can_create_for_arranger */
/** @var int $preset_owner_org_id */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
$cupName = (string) ($space['name'] ?? '');
$orgName = (string) ($arranger['name'] ?? 'Arrangør');
$orgId = (int) ($arranger['org_id'] ?? $preset_owner_org_id ?? 0);
$seasonName = is_array($season) ? trim((string) ($season['season_label'] ?? $season['name'] ?? '')) : '';
$seasonEvents = is_array($arranger['season_events'] ?? null) ? $arranger['season_events'] : [];
$allEvents = is_array($arranger['events'] ?? null) ? $arranger['events'] : [];
$linkedSeasons = is_array($arranger['seasons'] ?? null) ? $arranger['seasons'] : [];
$noSeasonEvents = (bool) ($arranger['missing_season_event'] ?? false);
$canCreate = (bool) ($can_create_for_arranger ?? false);
$seasonIds = [];
foreach ($seasonEvents as $ev) {
    $seasonIds[(int) ($ev['event_id'] ?? 0)] = true;
}
?>
<p class="muted" style="margin:0 0 .35rem;">
    <?= $h($cupName) ?>
    › <?= $h($orgName) ?>
</p>
<h1><?= $h($orgName) ?></h1>
<p class="muted">
    Arrangør i denne cupen basert på stevner organisasjonen har eller har hatt her —
    ikke global organisasjonsadministrasjon.
</p>
<?php if ($linkedSeasons !== []): ?>
    <div class="card">
        <p style="margin:0;">
            <strong>Sesonger arrangøren er knyttet til:</strong>
            <?= $h(implode(', ', array_map('strval', $linkedSeasons))) ?>
        </p>
    </div>
<?php endif; ?>

<?php if ($noSeasonEvents): ?>
    <div class="card">
        <p>
            <?php if ($seasonName !== ''): ?>
                Ingen stevner i <?= $h($seasonName) ?>.
            <?php else: ?>
                Ingen stevner i valgt sesong.
            <?php endif; ?>
            Tidligere arrangør — ingen stevner i valgt sesong.
        </p>
        <?php if ($canCreate): ?>
            <p style="margin-top:.75rem;">
                <a class="btn" href="<?= $h($pp::arrangorNyttStevne()) ?>?owner_org_id=<?= $orgId ?>">
                    Opprett stevne for ny arrangør
                </a>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0; font-size:1.05rem;">
        <?= $h($labels->plural('event')) ?> i
        <?= $seasonName !== '' ? $h($seasonName) : 'valgt sesong' ?>
    </h2>
    <?php if ($seasonEvents === []): ?>
        <p class="muted">Ingen stevner i <?= $seasonName !== '' ? $h($seasonName) : 'valgt sesong' ?>.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Navn</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($seasonEvents as $event): ?>
                <tr>
                    <td><?= $h((string) ($event['name'] ?? '')) ?></td>
                    <td><?= $h((string) ($event['status'] ?? '')) ?></td>
                    <td><a class="btn" href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">Åpne stevne</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$other = [];
foreach ($allEvents as $event) {
    $eid = (int) ($event['event_id'] ?? 0);
    if ($eid > 0 && !isset($seasonIds[$eid])) {
        $other[] = $event;
    }
}
?>
<?php if ($other !== []): ?>
    <div class="card">
        <h2 style="margin-top:0; font-size:1.05rem;">Andre stevner i cupen</h2>
        <ul>
            <?php foreach ($other as $event): ?>
                <li>
                    <a href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">
                        <?= $h((string) ($event['name'] ?? '')) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p>
    <a class="btn secondary" href="<?= $h($pp::arrangorer()) ?>">← Alle arrangører</a>
    <a class="btn secondary" href="<?= $h($pp::stevner()) ?>">Til stevner</a>
</p>
