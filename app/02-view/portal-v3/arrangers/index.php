<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var array<string, mixed>|null $season */
/** @var list<array<string, mixed>> $arrangers */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var string $route_prefix */
/** @var bool $can_create_for_arranger */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
$seasonName = is_array($season) ? trim((string) ($season['season_label'] ?? $season['name'] ?? '')) : '';
$seasonShort = $seasonName !== '' ? $seasonName : 'valgt sesong';
$withoutSeason = array_values(array_filter(
    $arrangers,
    static fn (array $a): bool => (bool) ($a['missing_season_event'] ?? false),
));
$canCreate = (bool) ($can_create_for_arranger ?? false);
?>
<h1>Arrangører</h1>
<p class="muted">
    Arrangører identifiseres gjennom stevner de har eller har hatt i denne cupen.
    Organisasjoner uten noe stevne i cupen vises ikke.
    Statusen under betyr historikk i cupen, men ingen stevner i <?= $h($seasonShort) ?>.
</p>

<?php if ($canCreate): ?>
    <p style="margin:0 0 1rem;">
        <a class="btn" href="<?= $h($pp::arrangorNyttStevne()) ?>">
            Opprett stevne for ny arrangør
        </a>
    </p>
<?php endif; ?>

<?php if ($withoutSeason !== []): ?>
    <div class="card">
        <p>
            <strong><?= count($withoutSeason) ?></strong>
            <?= count($withoutSeason) === 1 ? 'tidligere arrangør' : 'tidligere arrangører' ?>
            — ingen stevner i <?= $h($seasonShort) ?>.
        </p>
    </div>
<?php endif; ?>

<?php if ($arrangers === []): ?>
    <div class="card">
        <p>Ingen organisasjoner har stevner i denne cupen ennå. De dukker opp når et stevne er opprettet med organisasjonen som arrangør.</p>
        <?php if ($canCreate): ?>
            <p style="margin-top:.75rem;">
                <a class="btn" href="<?= $h($pp::arrangorNyttStevne()) ?>">
                    Opprett stevne for ny arrangør
                </a>
            </p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($arrangers as $arranger): ?>
        <?php
        $orgId = (int) ($arranger['org_id'] ?? 0);
        $seasonEvents = is_array($arranger['season_events'] ?? null) ? $arranger['season_events'] : [];
        $seasonCount = (int) ($arranger['season_event_count'] ?? count($seasonEvents));
        $noSeasonEvents = (bool) ($arranger['missing_season_event'] ?? false);
        $linkedSeasons = is_array($arranger['seasons'] ?? null) ? $arranger['seasons'] : [];
        ?>
        <div class="card">
            <h2 style="margin:0 0 .5rem; font-size:1.1rem;">
                <a href="<?= $h($pp::arrangor($orgId)) ?>">
                    <?= $h((string) ($arranger['name'] ?? '')) ?>
                </a>
            </h2>
            <?php if ($linkedSeasons !== []): ?>
                <p class="muted" style="margin:0 0 .5rem;">
                    Sesonger: <?= $h(implode(', ', array_map('strval', $linkedSeasons))) ?>
                </p>
            <?php endif; ?>
            <?php if ($noSeasonEvents): ?>
                <p class="muted">
                    <?php if ($seasonName !== ''): ?>
                        Ingen stevner i <?= $h($seasonName) ?>
                    <?php else: ?>
                        Ingen stevner i valgt sesong
                    <?php endif; ?>
                    — tidligere arrangør i cupen.
                </p>
            <?php else: ?>
                <p class="muted"><?= $seasonCount ?> <?= $h(strtolower($seasonCount === 1 ? $labels->singular('event') : $labels->plural('event'))) ?>
                    <?= $seasonName !== '' ? ' i ' . $h($seasonName) : '' ?>.</p>
                <ul style="margin:.35rem 0 0; padding-left:1.1rem;">
                    <?php foreach ($seasonEvents as $event): ?>
                        <li>
                            <a href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">
                                <?= $h((string) ($event['name'] ?? '')) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p style="margin-top:.75rem;">
                <a class="btn" href="<?= $h($pp::arrangor($orgId)) ?>">Åpne arrangør</a>
            </p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
