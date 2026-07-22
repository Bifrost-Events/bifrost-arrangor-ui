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
/** @var list<array<string, mixed>> $season_blocks */
/** @var \App\Service\PortalEventTerminology $labels */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$organizers = is_array($organizers ?? null) ? $organizers : [];
$seasonLabel = trim((string) ($season_label ?? ''));
$filterSeason = (string) ($filter_season_scope ?? 'selected');
$isArrangerView = (bool) ($is_arranger_view ?? false);
$arrangerName = trim((string) ($arranger_name ?? ''));
$seasonBlocks = is_array($season_blocks ?? null) ? $season_blocks : [];

$statusLabel = static function (string $status): string {
    return match ($status) {
        'draft' => 'Utkast',
        'published', 'active' => 'Publisert',
        'completed' => 'Fullført',
        'cancelled', 'inactive' => 'Avlyst',
        default => $status !== '' ? $status : '—',
    };
};

$renderEventTiles = static function (array $blockEvents) use ($h, $pp, $statusLabel): void {
    if ($blockEvents === []) {
        return;
    }
    echo '<div class="event-tiles">';
    foreach ($blockEvents as $event) {
        $eid = (int) ($event['event_id'] ?? 0);
        $ename = (string) ($event['name'] ?? 'Stevne');
        $estart = trim((string) ($event['starts_at'] ?? ''));
        $estatus = $statusLabel((string) ($event['status'] ?? ''));
        echo '<article class="event-tile">';
        echo '<a class="event-tile-title" href="' . $h($pp::stevne($eid)) . '">' . $h($ename) . '</a>';
        if ($estart !== '') {
            echo '<span class="event-tile-meta">' . $h($estart) . '</span>';
        }
        echo '<span class="event-tile-status">' . $h($estatus) . '</span>';
        echo '<div class="event-tile-actions">';
        echo '<a href="' . $h($pp::stevne($eid)) . '">Åpne</a>';
        echo '<a href="' . $h($pp::stevnePameldinger($eid)) . '">Påmeldinger</a>';
        echo '<a href="' . $h($pp::stevneJaktfelt($eid)) . '">Jaktfelt</a>';
        echo '</div></article>';
    }
    echo '</div>';
};
?>
<?php if ($isArrangerView): ?>
<style>
    .event-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
        gap: 1rem;
        margin-top: .75rem;
    }
    .event-tile {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        background: #f7f8f6;
        border-radius: 8px;
        padding: 1.1rem 1.2rem;
        border: 1px solid transparent;
        min-height: 7.5rem;
    }
    .event-tile:hover {
        border-color: var(--accent, #3d6b47);
        box-shadow: 0 4px 14px rgba(0,0,0,.08);
    }
    .event-tile .event-tile-title {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--ink, #1a1a18);
        text-decoration: none;
    }
    .event-tile .event-tile-title:hover { color: var(--accent, #3d6b47); }
    .event-tile .event-tile-meta {
        font-size: .85rem;
        color: var(--muted, #5c635c);
    }
    .event-tile .event-tile-status {
        font-size: .78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--accent, #3d6b47);
    }
    .event-tile .event-tile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: auto;
        padding-top: .55rem;
    }
    .event-tile .event-tile-actions a {
        font-size: .82rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--accent, #3d6b47);
        padding: .2rem .45rem;
        border-radius: 4px;
        background: #eef4ef;
    }
    .event-tile .event-tile-actions a:hover {
        background: #dfeadf;
    }
    .season-block {
        margin-top: 1.5rem;
        padding: 1.15rem 1.25rem 1.25rem;
        background: var(--card, #fff);
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .season-block:first-of-type { margin-top: 1rem; }
    .season-block-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .25rem;
    }
    .season-block-header h2 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
    }
    .season-block-empty {
        margin: .75rem 0 0;
        color: var(--muted, #5c635c);
    }
    .round-block {
        margin-top: 1.1rem;
        padding-top: .85rem;
        border-top: 1px solid #e6e8e4;
    }
    .round-block:first-of-type {
        margin-top: .85rem;
    }
    .round-block-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }
    .round-block-header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 650;
    }
    .round-block-header a.btn-link {
        font-size: .88rem;
        font-weight: 600;
        color: var(--accent, #3d6b47);
        text-decoration: none;
    }
    .round-block-header a.btn-link:hover { text-decoration: underline; }
</style>

<h1><?= $h($labels->plural('event')) ?></h1>
<p class="muted">
    <?php if ($arrangerName !== ''): ?>
        Stevner for <?= $h($arrangerName) ?> i <?= $h((string) ($space['name'] ?? 'cupen')) ?>
    <?php else: ?>
        Dine stevner i <?= $h((string) ($space['name'] ?? 'cupen')) ?>
    <?php endif; ?>.
</p>

<?php if ($seasonBlocks === []): ?>
    <div class="card" style="margin-top:1rem;">
        <p style="margin-top:0;">Ingen sesong tilgjengelig for denne arrangøren ennå.</p>
        <p class="muted">Når du er godkjent for en sesong, dukker den opp her med mulighet til å opprette stevne.</p>
    </div>
<?php else: ?>
    <?php foreach ($seasonBlocks as $block): ?>
        <?php
        $blockLabel = trim((string) ($block['label'] ?? 'Sesong'));
        $blockEvents = is_array($block['events'] ?? null) ? $block['events'] : [];
        $rounds = is_array($block['rounds'] ?? null) ? $block['rounds'] : [];
        $createHref = isset($block['create_href']) && is_string($block['create_href']) && $block['create_href'] !== ''
            ? $block['create_href']
            : null;
        $createBatchHref = isset($block['create_batch_href']) && is_string($block['create_batch_href']) && $block['create_batch_href'] !== ''
            ? $block['create_batch_href']
            : null;
        $hasRounds = $rounds !== [];
        ?>
        <section class="season-block" aria-label="<?= $h($blockLabel) ?>">
            <div class="season-block-header">
                <h2><?= $h($blockLabel) ?></h2>
                <?php if ($createBatchHref !== null): ?>
                    <a class="btn" href="<?= $h($createBatchHref) ?>">Opprett stevner</a>
                <?php elseif ($createHref !== null): ?>
                    <a class="btn" href="<?= $h($createHref) ?>">Opprett stevne</a>
                <?php endif; ?>
            </div>

            <?php if ($hasRounds): ?>
                <?php foreach ($rounds as $round): ?>
                    <?php
                    $roundLabel = trim((string) ($round['label'] ?? 'Runde'));
                    $roundEvents = is_array($round['events'] ?? null) ? $round['events'] : [];
                    $roundCreate = isset($round['create_href']) && is_string($round['create_href']) && $round['create_href'] !== ''
                        ? $round['create_href']
                        : null;
                    ?>
                    <div class="round-block">
                        <div class="round-block-header">
                            <h3><?= $h($roundLabel) ?></h3>
                            <?php if ($roundCreate !== null): ?>
                                <a class="btn-link" href="<?= $h($roundCreate) ?>">Opprett for denne runden</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($roundEvents === []): ?>
                            <p class="season-block-empty">Ingen stevner i denne runden ennå.</p>
                        <?php else: ?>
                            <?php $renderEventTiles($roundEvents); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php elseif ($blockEvents === []): ?>
                <p class="season-block-empty">Ingen stevner i denne sesongen ennå.</p>
                <?php if ($createHref !== null): ?>
                    <p style="margin-bottom:0;">
                        <a href="<?= $h($createHref) ?>">Opprett første stevne</a>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <?php $renderEventTiles($blockEvents); ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
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
                    <td><?= $h($statusLabel((string) ($event['status'] ?? ''))) ?></td>
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
