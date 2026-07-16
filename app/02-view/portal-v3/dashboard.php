<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed> $access */
/** @var array<string, mixed>|null $season */
/** @var string $season_label */
/** @var int $event_count */
/** @var list<array<string, mixed>> $upcoming_events */
/** @var int $arranger_count */
/** @var list<array<string, mixed>> $missing_arrangers */
/** @var list<array<string, mixed>> $my_organizations */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var array<string, mixed> $work_context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$cupName = (string) ($space['name'] ?? 'Cup');
$isCupAdminView = (bool) ($access['can_manage_cup'] ?? false);
$seasonName = trim($season_label !== '' ? $season_label : (string) ($season['name'] ?? ''));
$work = is_array($work_context ?? null) ? $work_context : [];
$workLabel = trim((string) ($work['label'] ?? ''));
$workDetail = trim((string) ($work['detail'] ?? ''));
?>
<h1>Oversikt</h1>
<p class="muted">
    <?= $h($cupName) ?>
    <?php if ($workLabel !== ''): ?>
        · <?= $h($workLabel) ?><?= $workDetail !== '' && $workDetail !== $cupName ? ' (' . $h($workDetail) . ')' : '' ?>
    <?php endif; ?>
</p>

<?php if ($isCupAdminView): ?>
    <div class="card">
        <p><strong>Valgt sesong:</strong> <?= $seasonName !== '' ? $h($seasonName) : 'Ikke satt' ?></p>
        <p><strong>Arrangører:</strong> <?= (int) $arranger_count ?>
            <?php if ($missing_arrangers !== []): ?>
                — <span style="color:#9b2c2c;"><?= count($missing_arrangers) ?> med ingen stevner i valgt sesong</span>
            <?php endif; ?>
        </p>
        <p><strong>Registrerte <?= $h(strtolower($labels->plural('event'))) ?>:</strong> <?= (int) $event_count ?></p>
    </div>
<?php else: ?>
    <div class="card">
        <p><strong>Valgt sesong:</strong> <?= $seasonName !== '' ? $h($seasonName) : 'Ikke satt' ?></p>
        <?php if ($my_organizations !== []): ?>
            <p><strong>Arrangør:</strong>
                <?php
                $names = array_map(
                    static fn (array $o): string => (string) ($o['name'] ?? ''),
                    $my_organizations,
                );
                echo $h(implode(', ', array_filter($names)));
                ?>
            </p>
        <?php endif; ?>
        <p><strong>Dine <?= $h(strtolower($labels->plural('event'))) ?> i sesongen:</strong> <?= (int) $event_count ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0; font-size:1.05rem;">
        <?= $isCupAdminView ? 'Kommende' : 'Mine kommende' ?>
        <?= $h(strtolower($labels->plural('event'))) ?>
    </h2>
    <?php if ($upcoming_events === []): ?>
        <p class="muted">
            Ingen kommende stevner
            <?= $seasonName !== '' ? ' i ' . $h($seasonName) : '' ?>.
            <?php if (!$isCupAdminView): ?>
                Bytt sesong eller sjekk at stevnene er lagret på denne arrangøren.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <table>
            <thead><tr><th>Navn</th><th>Arrangør</th><th>Start</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($upcoming_events as $event): ?>
                <tr>
                    <td><?= $h((string) ($event['name'] ?? '')) ?></td>
                    <td><?= $h((string) ($event['owner_org_name'] ?? '')) ?></td>
                    <td><?= $h((string) ($event['starts_at'] ?? '')) ?></td>
                    <td><a class="btn" href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">Åpne stevne</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
