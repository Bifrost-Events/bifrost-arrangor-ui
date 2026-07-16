<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var list<array<string, mixed>> $events */
/** @var array<int, string> $organizers */
/** @var int $filter_organizer_id */
/** @var string $filter_status */
/** @var string $filter_when */
/** @var string $filter_season_scope */
/** @var string $season_label */
/** @var bool $is_arranger_view */
/** @var string $arranger_name */
/** @var \App\Service\PortalEventTerminology $labels */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$organizers = is_array($organizers ?? null) ? $organizers : [];
$seasonLabel = trim((string) ($season_label ?? ''));
$filterSeason = (string) ($filter_season_scope ?? 'selected');
$isArrangerView = (bool) ($is_arranger_view ?? false);
$arrangerName = trim((string) ($arranger_name ?? ''));
$linkedSeasons = [];
foreach ($events as $event) {
    $sn = trim((string) ($event['season_name'] ?? ''));
    if ($sn !== '') {
        $linkedSeasons[$sn] = true;
    }
}
$linkedSeasons = array_keys($linkedSeasons);
sort($linkedSeasons, SORT_STRING);

$statusLabel = static function (string $status): string {
    return match ($status) {
        'draft' => 'Utkast',
        'published', 'active' => 'Publisert',
        'completed' => 'Fullført',
        'cancelled', 'inactive' => 'Avlyst',
        default => $status !== '' ? $status : '—',
    };
};
?>
<?php if ($isArrangerView): ?>
<style>
    .event-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    a.event-tile {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        background: var(--card, #fff);
        border-radius: 8px;
        padding: 1.1rem 1.2rem;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        text-decoration: none;
        color: inherit;
        border: 1px solid transparent;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        min-height: 7.5rem;
    }
    a.event-tile:hover {
        border-color: var(--accent, #3d6b47);
        box-shadow: 0 4px 14px rgba(0,0,0,.1);
        transform: translateY(-1px);
    }
    a.event-tile .event-tile-title {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--ink, #1a1a18);
    }
    a.event-tile .event-tile-meta {
        font-size: .85rem;
        color: var(--muted, #5c635c);
    }
    a.event-tile .event-tile-status {
        margin-top: auto;
        font-size: .78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--accent, #3d6b47);
    }
</style>

<h1><?= $h($labels->plural('event')) ?></h1>
<p class="muted">
    <?php if ($arrangerName !== ''): ?>
        Stevner for <?= $h($arrangerName) ?> i <?= $h((string) ($space['name'] ?? 'cupen')) ?>
    <?php else: ?>
        Dine stevner i <?= $h((string) ($space['name'] ?? 'cupen')) ?>
    <?php endif; ?>.
</p>
<?php if ($linkedSeasons !== []): ?>
    <p class="muted" style="margin-top:-.5rem;">
        Sesonger: <strong><?= $h(implode(', ', $linkedSeasons)) ?></strong>
    </p>
<?php endif; ?>

<?php if ($events === []): ?>
    <div class="card">
        <p>Ingen stevner for denne arrangøren i cupen ennå.</p>
    </div>
<?php else: ?>
    <div class="event-tiles">
        <?php foreach ($events as $event): ?>
            <?php
            $eid = (int) ($event['event_id'] ?? 0);
            $ename = (string) ($event['name'] ?? 'Stevne');
            $eseason = trim((string) ($event['season_name'] ?? ''));
            $estart = trim((string) ($event['starts_at'] ?? ''));
            $estatus = $statusLabel((string) ($event['status'] ?? ''));
            ?>
            <a class="event-tile" href="<?= $h($pp::stevne($eid)) ?>">
                <span class="event-tile-title"><?= $h($ename) ?></span>
                <?php if ($eseason !== ''): ?>
                    <span class="event-tile-meta"><?= $h($eseason) ?></span>
                <?php endif; ?>
                <?php if ($estart !== ''): ?>
                    <span class="event-tile-meta"><?= $h($estart) ?></span>
                <?php endif; ?>
                <span class="event-tile-status"><?= $h($estatus) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php else: ?>
<h1><?= $h($labels->plural('event')) ?></h1>
<p class="muted">
    Operativ oversikt for <?= $h((string) ($space['name'] ?? 'cupen')) ?>
    <?php if ($seasonLabel !== '' && $filterSeason !== 'all'): ?>
        — viser <?= $h($seasonLabel) ?>
    <?php elseif ($filterSeason === 'all'): ?>
        — alle sesonger
    <?php endif; ?>.
</p>

<div class="card">
    <form method="get" action="<?= $h($pp::stevner()) ?>" style="display:grid; gap:.75rem; max-width:40rem;">
        <div>
            <label for="season_scope">Sesong</label>
            <select id="season_scope" name="season_scope">
                <option value="selected" <?= $filterSeason !== 'all' ? 'selected' : '' ?>>
                    Valgt sesong<?= $seasonLabel !== '' ? ' (' . $h($seasonLabel) . ')' : '' ?>
                </option>
                <option value="all" <?= $filterSeason === 'all' ? 'selected' : '' ?>>Alle sesonger i cupen</option>
            </select>
        </div>
        <div>
            <label for="organizer_id">Arrangør</label>
            <select id="organizer_id" name="organizer_id">
                <option value="0">Alle</option>
                <?php foreach ($organizers as $oid => $oname): ?>
                    <option value="<?= (int) $oid ?>" <?= (int) $filter_organizer_id === (int) $oid ? 'selected' : '' ?>>
                        <?= $h($oname) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['' => 'Alle', 'draft' => 'Utkast', 'published' => 'Publisert', 'completed' => 'Fullført', 'cancelled' => 'Avlyst'] as $value => $label): ?>
                    <option value="<?= $h((string) $value) ?>" <?= $filter_status === (string) $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="when">Tid</label>
            <select id="when" name="when">
                <?php foreach (['' => 'Alle', 'upcoming' => 'Kommende', 'past' => 'Gjennomførte'] as $value => $label): ?>
                    <option value="<?= $h($value) ?>" <?= $filter_when === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p><button type="submit" class="btn">Filtrer</button></p>
    </form>
</div>

<div class="card">
    <?php if ($events === []): ?>
        <p>Ingen <?= $h(strtolower($labels->plural('event'))) ?> matcher filteret.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Navn</th>
                    <th>Sesong</th>
                    <th>Arrangør</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?= $h((string) ($event['name'] ?? '')) ?></td>
                    <td class="muted"><?= $h((string) ($event['season_name'] ?? '—')) ?></td>
                    <td class="muted"><?= $h((string) ($event['owner_org_name'] ?? '')) ?></td>
                    <td><?= $h((string) ($event['status'] ?? '')) ?></td>
                    <td><?= $h((string) ($event['starts_at'] ?? '')) ?></td>
                    <td>
                        <a class="btn" href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">
                            Åpne stevne
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>
