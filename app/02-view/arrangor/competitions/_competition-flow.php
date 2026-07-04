<?php

declare(strict_types=1);

/** @var int $competition_id */
/** @var array<string, mixed> $competition */
/** @var string $active_view */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$competitionId = (int) ($competition_id ?? 0);
$competition = is_array($competition ?? null) ? $competition : [];
$activeView = (string) ($active_view ?? 'pameldelse');
if (!in_array($activeView, ['oppsett', 'pameldelse', 'gjennomfor'], true)) {
    $activeView = 'pameldelse';
}

$compName = (string) ($competition['name'] ?? 'Stevne');
$compDate = (string) ($competition['competition_date'] ?? $competition['event_date'] ?? '');
$compLocation = (string) ($competition['location'] ?? '');

$dateLabel = '';
if ($compDate !== '' && strtotime($compDate) !== false) {
    $dateLabel = date('d.m.Y', strtotime($compDate));
}

?>
<style>
    .comp-flow { margin: 0 0 1.25rem; }
    .comp-flow-back { margin: 0 0 0.65rem; font-size: 0.9rem; }
    .comp-flow-head { margin: 0 0 0.75rem; }
    .comp-flow-title { margin: 0; font-size: 1.05rem; }
    .comp-flow-meta { color: var(--muted); font-weight: 400; }
    .comp-flow-steps {
        display: flex; flex-wrap: wrap; gap: 0.35rem; list-style: none; margin: 0; padding: 0;
    }
    .comp-flow-step a, .comp-flow-step span {
        display: inline-block; padding: 0.45rem 0.9rem; border-radius: 4px;
        font-size: 0.9rem; font-weight: 600; text-decoration: none;
        border: 1px solid var(--line); background: #fff; color: var(--accent);
    }
    .comp-flow-step.is-current span {
        background: var(--accent); border-color: var(--accent); color: #fff;
    }
    .comp-flow-step a:hover { background: #f4f7f4; }
</style>

<nav class="comp-flow" aria-label="Stevnearbeidsflyt">
    <p class="comp-flow-back"><a href="/stevner">&larr; Tilbake til stevneliste</a></p>
    <div class="comp-flow-head">
        <p class="comp-flow-title">
            <strong><?= $h($compName) ?></strong>
            <?php if ($dateLabel !== ''): ?>
                <span class="comp-flow-meta"> · <?= $h($dateLabel) ?></span>
            <?php endif; ?>
            <?php if ($compLocation !== ''): ?>
                <span class="comp-flow-meta"> · <?= $h($compLocation) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <ol class="comp-flow-steps">
        <li class="comp-flow-step<?= $activeView === 'oppsett' ? ' is-current' : '' ?>">
            <?php if ($activeView === 'oppsett'): ?>
                <span>Oppsett</span>
            <?php else: ?>
                <a href="/stevner/<?= $competitionId ?>">Oppsett</a>
            <?php endif; ?>
        </li>
        <li class="comp-flow-step<?= $activeView === 'pameldelse' ? ' is-current' : '' ?>">
            <?php if ($activeView === 'pameldelse'): ?>
                <span>Påmelding</span>
            <?php else: ?>
                <a href="/stevner/<?= $competitionId ?>/stevneadmin?vis=pameldelse">Påmelding</a>
            <?php endif; ?>
        </li>
        <li class="comp-flow-step<?= $activeView === 'gjennomfor' ? ' is-current' : '' ?>">
            <?php if ($activeView === 'gjennomfor'): ?>
                <span>Gjennomføring</span>
            <?php else: ?>
                <a href="/stevner/<?= $competitionId ?>/stevneadmin?vis=gjennomfor">Gjennomføring</a>
            <?php endif; ?>
        </li>
    </ol>
</nav>
