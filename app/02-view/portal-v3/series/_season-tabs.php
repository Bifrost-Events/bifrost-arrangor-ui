<?php

declare(strict_types=1);

/** @var int $series_id */
/** @var string $active_tab oversigt|struktur|sammenlagt */
/** @var bool $show_season_tabs */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$seriesId = (int) ($series_id ?? 0);
$active = (string) ($active_tab ?? 'oversikt');
if (empty($show_season_tabs) || $seriesId < 1) {
    return;
}
$tabs = [
    'oversikt' => ['label' => 'Oversikt', 'href' => $pp::sesongEdit($seriesId)],
    'struktur' => ['label' => 'Struktur', 'href' => $pp::sesongStruktur($seriesId)],
    'sammenlagt' => ['label' => 'Sammenlagtregler', 'href' => $pp::sesongSammenlagt($seriesId)],
];
?>
<nav class="season-tabs" aria-label="Sesong">
    <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= $h($tab['href']) ?>" class="<?= $active === $key ? 'is-active' : '' ?>"><?= $h($tab['label']) ?></a>
    <?php endforeach; ?>
</nav>
<style>
.season-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .15rem 1rem;
    margin: 0 0 1.25rem;
    border-bottom: 1px solid rgba(0,0,0,.1);
}
.season-tabs a {
    display: inline-block;
    padding: .45rem .1rem .65rem;
    color: var(--muted, #5c635c);
    text-decoration: none;
    font-weight: 600;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}
.season-tabs a:hover { color: var(--ink, #1a1a18); }
.season-tabs a.is-active {
    color: var(--accent, #3d6b47);
    border-bottom-color: var(--accent, #3d6b47);
}
.season-tree-preview {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .85rem;
    white-space: pre;
    background: rgba(0,0,0,.03);
    padding: .85rem 1rem;
    border-radius: 4px;
    overflow-x: auto;
}
.form-warning {
    border: 1px solid #c05621;
    background: #fffaf0;
    color: #7b341e;
    padding: .75rem 1rem;
    border-radius: 4px;
    margin: .75rem 0;
}
.unsaved-hint { color: #9b2c2c; font-size: .9rem; display: none; }
.unsaved-hint.is-visible { display: inline; }
.scoring-summary {
    margin-top: 1rem;
    padding: .75rem 1rem;
    background: rgba(61,107,71,.08);
    border-radius: 4px;
}
</style>
