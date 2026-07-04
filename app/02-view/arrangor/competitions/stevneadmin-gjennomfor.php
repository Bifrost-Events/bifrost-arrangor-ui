<?php

declare(strict_types=1);

/** @var int $competition_id */
/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $stevne_admin */
/** @var array<string, mixed>|null $view_data */
/** @var array<string, mixed> $context */

use App\Support\StevneAdminViewData;

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$canWrite = (bool) ($context['can_write'] ?? false);
$data = is_array($view_data) ? $view_data : [];
$slotSummary = is_array($data['slot_summary'] ?? null) ? $data['slot_summary'] : [];
$slotRows = is_array($data['slot_rows'] ?? null) ? $data['slot_rows'] : [];
$selectedSlot = (int) ($data['selected_slot_number'] ?? 0);
$approved = (bool) ($data['stevneadmin_approved'] ?? false);
$approvedAt = isset($data['stevneadmin_approved_at']) ? (string) $data['stevneadmin_approved_at'] : '';
$prevSlot = (int) ($data['prev_slot_number'] ?? 0);
$nextSlot = (int) ($data['next_slot_number'] ?? 0);
$rosterLocked = (bool) ($data['selected_slot_roster_locked'] ?? false);
$resultsLocked = (bool) ($data['selected_slot_results_locked'] ?? false);
$editBlocked = $approved || $resultsLocked;
$rosterEditBlocked = $approved || $rosterLocked;
$canRosterManage = $canWrite && !$rosterEditBlocked;

$assignUrl = $selectedSlot > 0
    ? '/stevner/' . $competition_id . '/stevneadmin/lag/' . $selectedSlot . '/tilordne'
    : '';
$removeUrl = $selectedSlot > 0
    ? '/stevner/' . $competition_id . '/stevneadmin/lag/' . $selectedSlot . '/fjern'
    : '';
$participantSearchUrl = '/stevner/' . $competition_id . '/stevneadmin/sok-deltaker';

$competitionData = is_array($data['competition'] ?? null) ? $data['competition'] : [];
$figuresPerSlotMeta = (int) ($data['figures_per_slot'] ?? max(1, (int) ($competitionData['antall_skyttere_per_lag'] ?? 6)));
$saMeta = is_array($data['stevne_admin_meta'] ?? null)
    ? $data['stevne_admin_meta']
    : StevneAdminViewData::buildMeta($competitionData, $figuresPerSlotMeta);
$scoringMode = (string) ($saMeta['scoring_mode'] ?? 'njff');
$isNjff = $scoringMode !== 'dfs';
$saShowTb = (bool) ($saMeta['show_skillefigur'] ?? false);
$saTbOrder = is_array($saMeta['tiebreaker_figure_order'] ?? null) ? $saMeta['tiebreaker_figure_order'] : [];
$saTbCount = (int) ($saMeta['tiebreaker_field_count'] ?? 0);
$saModeLabel = strtoupper($scoringMode);

$saPrintStevne = trim((string) ($competitionData['name'] ?? ''));
$saPrintLoc = trim((string) ($competitionData['location'] ?? ''));
$saPrintDateRaw = (string) ($competitionData['competition_date'] ?? '');
$saPrintDate = $saPrintDateRaw !== '' ? date('d.m.Y', strtotime($saPrintDateRaw)) : '';
$saPrintSubParts = array_filter([$saPrintLoc, $saPrintDate], static fn (string $s): bool => $s !== '');
$saPrintSub = implode(' · ', $saPrintSubParts);
$slotLockUrl = '/stevner/' . $competition_id . '/stevneadmin/lag/' . $selectedSlot . '/laas';

$totalParticipants = 0;
$totalRegistered = 0;
foreach ($slotSummary as $slot) {
    $totalParticipants += (int) ($slot['participants'] ?? 0);
    $totalRegistered += (int) ($slot['with_score'] ?? 0);
}

$slotUrl = static fn (int $slotNum = 0): string => $slotNum > 0
    ? '/stevner/' . $competition_id . '/stevneadmin?vis=gjennomfor&lag=' . $slotNum
    : '/stevner/' . $competition_id . '/stevneadmin?vis=gjennomfor';

?>
<style>
    .sa-meta { color: var(--muted); margin: 0.25rem 0 1rem; }
    .sa-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 1rem 0; }
    .sa-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.85rem; margin-top: 1rem; }
    .sa-slot-card {
        border: 1px solid var(--line); border-radius: 6px; padding: 0.85rem;
        background: #fff; border-left: 4px solid #6b8cae;
    }
    .sa-slot-card.is-active { box-shadow: 0 0 0 2px var(--accent); }
    .sa-slot-card--complete { border-left-color: var(--ok); background: #f4faf5; }
    .sa-slot-card--partial { border-left-color: #c9a227; background: #fffbf0; }
    .sa-slot-card--pending { border-left-color: #6b8cae; }
    .sa-slot-card--empty { border-left-color: #b8bdb5; background: #f7f8f6; }
    .sa-slot-card--locked { box-shadow: inset 0 -3px 0 #8a5a44; }
    .sa-slot-card h4 { margin: 0 0 0.35rem; font-size: 1rem; }
    .sa-pill {
        display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
        font-size: 0.75rem; font-weight: 600; margin-right: 0.25rem;
    }
    .sa-pill--locked { background: #f3ebe6; color: #6b4226; }
    .sa-pill--complete { background: #e6f2e8; color: var(--ok); }
    .sa-pill--partial { background: #fff4e5; color: #8a5a00; }
    .sa-pill--pending { background: #e8f0f8; color: #1a4a6e; }
    .sa-pill--empty { background: #eef0eb; color: var(--muted); }
    .sa-panel { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--line); }
    .sa-table-wrap { overflow-x: auto; margin-top: 1rem; }
    .sa-result-table { min-width: 680px; width: 100%; border-collapse: collapse; }
    .sa-result-table th, .sa-result-table td { white-space: nowrap; padding: 0.35rem 0.4rem; }
    .sa-hold-pair { display: inline-flex; gap: 0.2rem; align-items: center; }
    .sa-hold-pair-inline { display: inline-flex; align-items: center; gap: 0.15rem; }
    .sa-hold-pair-inline span { color: var(--muted); font-size: 0.85rem; }
    .sa-hold-digit {
        width: 2.1rem; padding: 0.35rem 0.15rem; text-align: center;
        border: 1px solid var(--line); border-radius: 4px; font-size: 0.95rem;
    }
    .sa-hold-digit:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
    .sa-hold-digit.sa-hold-invalid { border-color: #b45309; background: #fff8eb; }
    .sa-hold-label { font-size: 0.72rem; color: var(--muted); margin-right: 0.15rem; }
    .sa-skillefig-th { font-size: 0.78rem; line-height: 1.15; }
    .sa-skillefig-sub { font-size: 0.68rem; color: var(--muted); font-weight: normal; }
    .sa-tb { width: 2.4rem; padding: 0.35rem 0.15rem; text-align: center; border: 1px solid var(--line); border-radius: 4px; font-size: 0.95rem; }
    .sa-row-score { font-weight: 600; }
    .sa-row-empty td { color: var(--muted); }
    .sa-legend { margin-top: 0.75rem; font-size: 0.85rem; }
    .sa-slot-modal {
        width: min(1400px, 98vw); max-height: 94vh; border: none; border-radius: 10px;
        padding: 0; margin: auto; background: #fff; color: inherit;
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.22);
    }
    .sa-slot-modal::backdrop { background: rgba(0, 0, 0, 0.5); }
    .sa-slot-modal-inner { padding: 1rem 1.15rem 1.25rem; overflow: auto; max-height: 94vh; }
    .sa-slot-modal-header {
        display: flex; justify-content: space-between; align-items: center;
        gap: 0.75rem; margin-bottom: 0.75rem;
    }
    .sa-slot-modal-header h3 { margin: 0; font-size: 1.25rem; }
    .sa-slot-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
        gap: 0.75rem; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);
        padding: 0.65rem 0; margin-bottom: 0.75rem;
    }
    .sa-slot-toolbar-left, .sa-slot-toolbar-right { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .btn-warn { background: #8a5a00; border-color: #8a5a00; color: #fff; }
    .btn[disabled], .btn-disabled { opacity: 0.55; pointer-events: none; }
    .sa-col-roster { white-space: nowrap; vertical-align: middle; }
    .sa-roster-btn {
        font-size: 0.75rem; padding: 0.2rem 0.55rem; border-radius: 4px;
        border: 1px solid var(--line); background: #fff; cursor: pointer;
    }
    .sa-roster-btn:hover { background: #f4f6f2; }
    .sa-roster-btn-danger { border-color: #b45309; color: #8a5a00; }
    .sa-roster-btn-danger:hover { background: #fff8eb; }
    .sa-roster-locked-hint { font-size: 0.72rem; color: var(--muted); }
    .sa-roster-dialog {
        border: none; border-radius: 8px; padding: 0; max-width: 26rem;
        width: calc(100vw - 2rem); background: #fff; color: inherit;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    }
    .sa-roster-dialog::backdrop { background: rgba(0, 0, 0, 0.45); }
    .sa-roster-dialog-inner { padding: 1rem 1.1rem 1.15rem; }
    .sa-roster-dialog-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.5rem; margin-bottom: 0.5rem;
    }
    .sa-roster-dialog-title { margin: 0; font-size: 1rem; }
    .sa-roster-search-input {
        width: 100%; box-sizing: border-box; padding: 0.45rem 0.6rem;
        margin: 0.35rem 0 0.65rem; border-radius: 4px; border: 1px solid var(--line);
    }
    .sa-roster-search-results { display: flex; flex-direction: column; gap: 0.35rem; max-height: 14rem; overflow-y: auto; }
    .sa-roster-pick-btn {
        text-align: left; padding: 0.45rem 0.6rem; border-radius: 4px;
        border: 1px solid var(--line); background: #fff; cursor: pointer;
    }
    .sa-roster-pick-btn:hover { background: #f4f6f2; }
    .sa-roster-search-hint { margin: 0.5rem 0 0; font-size: 0.85rem; }
    .sa-modal-close {
        border: none; background: transparent; font-size: 1.35rem; line-height: 1;
        cursor: pointer; color: var(--muted); padding: 0 0.15rem;
    }
    .sa-print-sheet-meta { margin-bottom: 0.65rem; }
    .sa-print-sheet-title { font-weight: 600; font-size: 1rem; }
    .sa-print-sheet-sub { font-size: 0.85rem; color: var(--muted); }
    .sa-class-missing { color: #8a5a00; font-size: 0.85rem; }
    .sa-row-class-warning td { background: #fff8eb; }
</style>

<p class="lead" style="margin-top:0;">Registrer resultater og godkjenn stevnet.</p>

<?php if (!($stevne_admin['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($stevne_admin['error'] ?? 'Kunne ikke hente gjennomføring.')) ?></p>
<?php else: ?>
    <div class="sa-toolbar">
        <?php if ($approved): ?>
            <span class="badge badge-ok">Stevnet er godkjent<?= $approvedAt !== '' ? ' (' . $h(date('d.m.Y H:i', strtotime($approvedAt))) . ')' : '' ?></span>
            <?php if ($canWrite): ?>
                <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/godkjenning" onsubmit="return confirm('Oppheve godkjenning? Da kan resultater redigeres igjen.');">
                    <?php if ($selectedSlot > 0): ?>
                        <input type="hidden" name="lag" value="<?= $selectedSlot ?>">
                    <?php endif; ?>
                    <button type="submit" name="unapprove_competition" value="1" class="btn btn-warn">Opphev godkjenning</button>
                </form>
            <?php endif; ?>
        <?php elseif ($canWrite && $slotSummary !== []): ?>
            <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/godkjenning" onsubmit="return confirm('Godkjenne stevnet? Resultater og påmelding kan da ikke endres før du opphever godkjenning.');">
                <?php if ($selectedSlot > 0): ?>
                    <input type="hidden" name="lag" value="<?= $selectedSlot ?>">
                <?php endif; ?>
                <button type="submit" name="approve_competition" value="1" class="btn btn-warn">Godkjenn stevne</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($slotSummary === []): ?>
        <div class="placeholder-box">
            <p class="muted">Ingen lag er opprettet ennå. Gå til <a href="/stevner/<?= $competition_id ?>/stevneadmin?vis=pameldelse">Påmelding</a> for å generere lag.</p>
        </div>
    <?php else: ?>
        <h3>Lagoversikt (<?= count($slotSummary) ?> lag)</h3>
        <p class="muted">
            Lag: <?= count($slotSummary) ?> · Skyttere: <?= $totalParticipants ?> · Registrert resultat: <?= $totalRegistered ?>/<?= $totalParticipants ?>
            <?php if ($selectedSlot > 0): ?> · Aktivt lag: <?= $selectedSlot ?><?php endif; ?>
        </p>

        <div class="sa-grid">
            <?php foreach ($slotSummary as $slot): ?>
                <?php
                $sn = (int) ($slot['slot_number'] ?? 0);
                $participants = (int) ($slot['participants'] ?? 0);
                $withScore = (int) ($slot['with_score'] ?? 0);
                $isActive = $selectedSlot === $sn;
                $cardClasses = ['sa-slot-card'];
                if ($isActive) {
                    $cardClasses[] = 'is-active';
                }
                if ($participants < 1) {
                    $cardClasses[] = 'sa-slot-card--empty';
                    $slotResultLabel = 'Ingen skyttere';
                } elseif ($withScore >= $participants) {
                    $cardClasses[] = 'sa-slot-card--complete';
                    $slotResultLabel = 'Alle har resultat';
                } elseif ($withScore > 0) {
                    $cardClasses[] = 'sa-slot-card--partial';
                    $slotResultLabel = 'Delvis resultat';
                } else {
                    $cardClasses[] = 'sa-slot-card--pending';
                    $slotResultLabel = 'Ingen resultat ennå';
                }
                if (($slot['is_roster_locked'] ?? false) || ($slot['is_locked'] ?? false)) {
                    $cardClasses[] = 'sa-slot-card--locked';
                }
                ?>
                <article class="<?= $h(implode(' ', $cardClasses)) ?>">
                    <h4>Lag <?= $sn ?></h4>
                    <p>
                        <?php if ($slot['is_roster_locked'] ?? false): ?>
                            <span class="sa-pill sa-pill--locked">Påmelding låst</span>
                        <?php endif; ?>
                        <?php if ($slot['is_locked'] ?? false): ?>
                            <span class="sa-pill sa-pill--locked">Resultat låst</span>
                        <?php endif; ?>
                        <span class="sa-pill sa-pill--<?= $participants < 1 ? 'empty' : ($withScore >= $participants ? 'complete' : ($withScore > 0 ? 'partial' : 'pending')) ?>">
                            <?= $h($slotResultLabel) ?>
                        </span>
                    </p>
                    <p class="muted" style="font-size:0.88rem;">
                        Start: <?= $h((string) ($slot['start_time'] ?? '–')) ?><br>
                        Skyttere: <?= $participants ?><br>
                        Resultat: <?= $withScore ?>/<?= $participants ?>
                    </p>
                    <a class="btn" href="<?= $h($slotUrl($sn)) ?>">Åpne lag</a>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="sa-legend muted">Farge på kort: grønn = alle har resultat, gul = delvis, blå = ingen resultat, grå = ingen skyttere.</p>

        <?php if ($selectedSlot > 0): ?>
            <dialog id="sa-slot-modal" class="sa-slot-modal" data-overview-url="<?= $h($slotUrl()) ?>">
                <div class="sa-slot-modal-inner">
                    <div class="sa-slot-modal-header">
                        <h3>Lag <?= $selectedSlot ?></h3>
                        <button type="button" class="sa-modal-close" id="sa-slot-modal-close" aria-label="Lukk">×</button>
                    </div>

                    <?php if ($approved): ?>
                        <p class="flash flash-success">Stevnet er godkjent. Opphev godkjenning for å endre resultater.</p>
                    <?php elseif ($resultsLocked || $rosterLocked): ?>
                        <p class="flash flash-info">
                            <?php if ($rosterLocked && $resultsLocked): ?>
                                Påmelding og resultater er låst på dette laget.
                            <?php elseif ($resultsLocked): ?>
                                Resultater er låst på dette laget.
                            <?php elseif ($rosterLocked): ?>
                                Påmelding er låst på dette laget.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <div class="sa-slot-toolbar">
                        <div class="sa-slot-toolbar-left">
                            <?php if ($prevSlot > 0): ?>
                                <a class="btn sa-slot-nav-link" href="<?= $h($slotUrl($prevSlot)) ?>">← Forrige lag</a>
                            <?php else: ?>
                                <span class="btn btn-disabled">← Forrige lag</span>
                            <?php endif; ?>
                            <?php if ($nextSlot > 0): ?>
                                <a class="btn sa-slot-nav-link" href="<?= $h($slotUrl($nextSlot)) ?>">Neste lag →</a>
                            <?php else: ?>
                                <span class="btn btn-disabled">Neste lag →</span>
                            <?php endif; ?>
                        </div>
                        <div class="sa-slot-toolbar-right">
                            <button type="button" class="btn" id="sa-slot-print-btn">Skriv ut lag</button>
                            <form id="sa-save-form" method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/lag/<?= $selectedSlot ?>/lagre" style="display:contents;">
                                <?php if ($nextSlot > 0 && $canWrite && !$editBlocked): ?>
                                    <button type="submit" class="btn" name="next_slot_number" value="<?= $nextSlot ?>">Lagre og neste lag</button>
                                <?php endif; ?>
                                <?php if ($canWrite && !$editBlocked): ?>
                                    <button type="submit" class="btn btn-primary">Lagre resultater</button>
                                <?php endif; ?>
                            </form>
                            <?php if ($canWrite && !$approved): ?>
                                <form method="post" action="<?= $h($slotLockUrl) ?>" style="display:contents;">
                                    <?php if ($rosterLocked): ?>
                                        <button type="submit" class="btn btn-warn" name="unlock_roster" value="1">Lås opp påmelding</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-warn" name="lock_roster" value="1">Lås påmeldinger</button>
                                    <?php endif; ?>
                                    <?php if ($resultsLocked): ?>
                                        <button type="submit" class="btn btn-warn" name="unlock_results" value="1">Lås opp resultater</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-warn" name="lock_results" value="1">Lås resultater</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="muted">
                        Registrer treff (T) og innertreff (I) for hvert hold. Cursor hopper automatisk til neste felt.
                        Format: <strong><?= $h($saModeLabel) ?></strong><?= $isNjff ? ' — poeng = (treff × 3) + (innertreff × 2) per hold' : ' — rangeres på sum treff/innertreff' ?>.
                        <?php if ($saShowTb): ?> Skillefigur registreres ved lik poengsum.<?php endif; ?>
                    </p>

                    <div class="sa-print-sheet">
                        <div class="sa-print-sheet-meta">
                            <?php if ($saPrintStevne !== ''): ?>
                                <div class="sa-print-sheet-title"><?= $h($saPrintStevne) ?></div>
                            <?php endif; ?>
                            <?php if ($saPrintSub !== ''): ?>
                                <div class="sa-print-sheet-sub"><?= $h($saPrintSub) ?></div>
                            <?php endif; ?>
                            <div class="sa-print-sheet-sub">
                                Lag <?= $selectedSlot ?> · <?= $h($saModeLabel) ?><?php if ($saShowTb && $saTbOrder !== []): ?> · skillefig.: <?= $h(implode(', ', $saTbOrder)) ?><?php endif; ?>
                            </div>
                        </div>
                    <div class="sa-table-wrap">
                        <table class="sa-result-table">
                            <thead>
                                <tr>
                                    <th scope="col" title="«OK» når skiven har registrert treff eller skillefigur.">Status</th>
                                    <th class="sa-col-figur">Figur</th>
                                    <th class="sa-col-navn">Navn</th>
                                    <th class="sa-col-klasse">Klasse</th>
                                    <th class="sa-col-roster">Påmelding</th>
                                    <?php for ($hi = 1; $hi <= 6; $hi++): ?>
                                        <th class="sa-col-hold">H<?= $hi ?></th>
                                        <?php if ($saShowTb && $saTbOrder !== []): ?>
                                            <?php foreach ($saTbOrder as $tbi => $holdFig): ?>
                                                <?php if ((int) $holdFig === $hi): ?>
                                                    <th class="sa-skillefig-th sa-col-hold">Skillefig.<br><span class="sa-skillefig-sub">H<?= $hi ?> · <?= $tbi + 1 ?></span></th>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($saShowTb && $saTbOrder === []): ?>
                                        <th class="sa-skillefig-th sa-col-hold">Skillefigur</th>
                                    <?php endif; ?>
                                    <?php if ($isNjff): ?>
                                        <th class="sa-col-poeng sa-screen-only">Poeng</th>
                                    <?php endif; ?>
                                    <th class="sa-col-total">Treff/Innertreff</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($slotRows as $idx => $row): ?>
                                <?php
                                $participantId = (int) ($row['participant_id'] ?? 0);
                                $figure = (int) ($row['figure_number'] ?? 0);
                                $slotId = (int) ($row['slot_id'] ?? 0);
                                $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                                $holds = StevneAdminViewData::holdsForDisplay($row['score_breakdown'] ?? null);
                                $tbVals = StevneAdminViewData::tiebreakerValuesForDisplay($row['score_breakdown'] ?? null, $saTbCount);
                                $totals = StevneAdminViewData::totalsFromHolds(
                                    StevneAdminViewData::normalizeHoldsForSave(['h' => $holds])
                                );
                                $isFilled = $participantId > 0;
                                $disAttr = ($editBlocked || !$canWrite) ? ' disabled' : '';
                                $hasHoldScore = StevneAdminViewData::rowHasScoringInput(['h' => $holds], $saTbCount)
                                    || ($saTbCount > 0 && $tbVals !== array_fill(0, $saTbCount, ''));
                                $canChangeRoster = $isFilled && !$hasHoldScore;
                                $classLabel = StevneAdminViewData::classLabelForRow($row);
                                $classMissing = $isFilled && (int) ($row['class_id'] ?? 0) < 1;
                                $rowStatus = StevneAdminViewData::rowStatus($isFilled, $holds, $tbVals, $saTbCount);
                                ?>
                                <tr class="<?= $classMissing ? 'sa-row-class-warning' : ($isFilled ? '' : 'sa-row-empty') ?>">
                                    <td><?= $h($rowStatus) ?></td>
                                    <td class="sa-col-figur"><?= $figure ?></td>
                                    <td class="sa-col-navn"><?= $isFilled ? $h($name !== '' ? $name : 'Ledig skive') : '–' ?></td>
                                    <td class="sa-col-klasse <?= $classMissing ? 'sa-class-missing' : '' ?>"<?= $classMissing ? ' title="Konkurranseklasse mangler."' : '' ?>><?= $h($classLabel) ?></td>
                                    <td class="sa-col-roster">
                                        <?php if ($canRosterManage): ?>
                                            <?php if (!$isFilled && $slotId > 0): ?>
                                                <button type="button" class="sa-roster-btn sa-roster-add-btn" data-sa-roster-slot-id="<?= $slotId ?>" data-sa-roster-figure="<?= $figure ?>">Legg til</button>
                                            <?php elseif ($isFilled && $canChangeRoster): ?>
                                                <form method="post" action="<?= $h($removeUrl) ?>" class="sa-roster-remove-form" onsubmit="return confirm('Fjerne deltaker fra påmeldingen til stevnet?');">
                                                    <input type="hidden" name="return_vis" value="gjennomfor">
                                                    <input type="hidden" name="figure_number" value="<?= $figure ?>">
                                                    <button type="submit" class="sa-roster-btn sa-roster-btn-danger">Fjern</button>
                                                </form>
                                            <?php elseif ($isFilled): ?>
                                                <span class="sa-roster-locked-hint" title="Kan ikke flyttes eller fjernes når det finnes registrerte resultater.">Låst</span>
                                            <?php else: ?>
                                                –
                                            <?php endif; ?>
                                        <?php elseif ($rosterEditBlocked): ?>
                                            <span class="sa-roster-locked-hint" title="Lås opp påmelding for å endre deltakere.">–</span>
                                        <?php else: ?>
                                            –
                                        <?php endif; ?>
                                    </td>
                                    <?php for ($hi = 1; $hi <= 6; $hi++): ?>
                                        <td class="sa-col-hold">
                                            <?php if ($isFilled): ?>
                                                <?php if ($hi === 1): ?>
                                                    <input type="hidden" form="sa-save-form" name="rows[<?= $idx ?>][participant_id]" value="<?= $participantId ?>">
                                                    <input type="hidden" form="sa-save-form" name="rows[<?= $idx ?>][slot_id]" value="<?= $slotId ?>">
                                                    <input type="hidden" form="sa-save-form" name="rows[<?= $idx ?>][slot_number]" value="<?= $selectedSlot ?>">
                                                    <input type="hidden" form="sa-save-form" name="rows[<?= $idx ?>][figure_number]" value="<?= $figure ?>">
                                                <?php endif; ?>
                                                <div class="sa-hold-pair-inline">
                                                    <input type="text" form="sa-save-form" inputmode="numeric" maxlength="1" autocomplete="off" class="sa-hold-digit sa-focus-chain" data-hold-part="t" name="rows[<?= $idx ?>][h][<?= $hi ?>][t]" value="<?= $h($holds[$hi - 1]['t']) ?>" placeholder="T" title="Treff 0–6" aria-label="H<?= $hi ?> treff"<?= $disAttr ?>>
                                                    <span>/</span>
                                                    <input type="text" form="sa-save-form" inputmode="numeric" maxlength="1" autocomplete="off" class="sa-hold-digit sa-focus-chain" data-hold-part="i" name="rows[<?= $idx ?>][h][<?= $hi ?>][i]" value="<?= $h($holds[$hi - 1]['i']) ?>" placeholder="I" title="Innertreff ≤ treff" aria-label="H<?= $hi ?> innertreff"<?= $disAttr ?>>
                                                </div>
                                            <?php else: ?>
                                                –
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($saShowTb && $saTbOrder !== []): ?>
                                            <?php foreach ($saTbOrder as $tbi => $holdFig): ?>
                                                <?php if ((int) $holdFig === $hi): ?>
                                                    <td class="sa-col-hold">
                                                        <?php if ($isFilled): ?>
                                                            <input type="text" form="sa-save-form" inputmode="numeric" maxlength="2" autocomplete="off" class="sa-focus-chain sa-tb" placeholder="–" title="Skillefigur 0–99" name="rows[<?= $idx ?>][tb][<?= $tbi ?>]" value="<?= $h($tbVals[$tbi] ?? '') ?>" aria-label="Skillefigur H<?= $hi ?>"<?= $disAttr ?>>
                                                        <?php else: ?>
                                                            –
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <?php if ($isFilled && $saShowTb && $saTbOrder === []): ?>
                                        <td class="sa-col-hold">
                                            <input type="text" form="sa-save-form" inputmode="numeric" maxlength="2" autocomplete="off" class="sa-focus-chain sa-tb" placeholder="–" title="Skillefigur 0–99" name="rows[<?= $idx ?>][tb][0]" value="<?= $h($tbVals[0] ?? '') ?>"<?= $disAttr ?>>
                                        </td>
                                    <?php elseif ($saShowTb && $saTbOrder === []): ?>
                                        <td class="sa-col-hold">–</td>
                                    <?php endif; ?>
                                    <?php if ($isNjff): ?>
                                        <td class="sa-row-score sa-col-poeng sa-screen-only">
                                            <?php if ($isFilled && ($totals['score'] !== null || $hasHoldScore)): ?>
                                                <?= (int) ($totals['score'] ?? 0) ?>
                                            <?php else: ?>
                                                –
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="sa-row-total sa-col-total">
                                        <?php if ($isFilled && ($totals['hits'] !== null || $totals['inner_hits'] !== null)): ?>
                                            <?= (int) ($totals['hits'] ?? 0) ?>/<?= (int) ($totals['inner_hits'] ?? 0) ?>
                                        <?php else: ?>
                                            –
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
            </dialog>

            <?php if ($canRosterManage && $assignUrl !== ''): ?>
                <dialog id="sa-roster-assign-dialog" class="sa-roster-dialog">
                    <div class="sa-roster-dialog-inner">
                        <div class="sa-roster-dialog-head">
                            <h4 class="sa-roster-dialog-title">Legg til deltaker</h4>
                            <button type="button" class="sa-modal-close" id="sa-roster-assign-close" aria-label="Lukk">×</button>
                        </div>
                        <p class="muted sa-roster-dialog-help">Søk på navn (min. to tegn). Er personen allerede påmeldt stevnet, flyttes hen til skive <strong id="sa-roster-figure-label">–</strong> (kun uten registrerte resultater).</p>
                        <label for="sa-roster-search">Søk</label>
                        <input type="search" id="sa-roster-search" class="sa-roster-search-input" autocomplete="off" placeholder="Etternavn eller fornavn">
                        <div id="sa-roster-search-results" class="sa-roster-search-results" aria-live="polite"></div>
                        <form id="sa-roster-assign-form" method="post" action="<?= $h($assignUrl) ?>" style="display:none;">
                            <input type="hidden" name="return_vis" value="gjennomfor">
                            <input type="hidden" name="slot_id" id="sa-roster-assign-slot-id" value="">
                            <input type="hidden" name="figure_number" id="sa-roster-assign-figure" value="">
                            <input type="hidden" name="participant_id" id="sa-roster-assign-participant-id" value="">
                        </form>
                        <p id="sa-roster-search-hint" class="muted sa-roster-search-hint"></p>
                    </div>
                </dialog>
                <div id="sa-roster-config" style="display:none;" data-participant-search-url="<?= $h($participantSearchUrl) ?>"></div>
            <?php endif; ?>

            <script>
            (function () {
                var isNjff = <?= $isNjff ? 'true' : 'false' ?>;
                var slotModal = document.getElementById("sa-slot-modal");
                var slotCloseBtn = document.getElementById("sa-slot-modal-close");
                var slotFormDirty = false;
                var overviewUrl = slotModal ? (slotModal.getAttribute("data-overview-url") || "") : "";

                function parseHoldDigit(val) {
                    var s = String(val || "").trim();
                    if (s === "") return null;
                    var n = parseInt(s, 10);
                    return Number.isFinite(n) ? n : null;
                }

                function holdPoeng(t, inn) {
                    if (t === null && inn === null) return 0;
                    var ti = t !== null ? t : 0;
                    var ii = inn !== null ? inn : 0;
                    return (ti * 3) + (ii * 2);
                }

                function computeRowHoldTotals(tr) {
                    var totalT = 0;
                    var totalI = 0;
                    var totalScore = 0;
                    var has = false;
                    tr.querySelectorAll(".sa-hold-pair-inline, .sa-hold-pair").forEach(function (wrap) {
                        var inputs = wrap.querySelectorAll("input.sa-hold-digit");
                        if (inputs.length < 2) return;
                        var t = parseHoldDigit(inputs[0].value);
                        var inn = parseHoldDigit(inputs[1].value);
                        if (t === null && inn === null) return;
                        if (t !== null && inn !== null && inn > t) {
                            inn = t;
                        }
                        has = true;
                        totalT += t !== null ? t : 0;
                        totalI += inn !== null ? inn : 0;
                        totalScore += holdPoeng(t, inn);
                    });
                    return { hasHoldScore: has, totalTreff: totalT, totalInner: totalI, totalScore: totalScore };
                }

                function updateRowTotals(tr) {
                    if (!tr) return;
                    var totals = computeRowHoldTotals(tr);
                    var totalCell = tr.querySelector("td.sa-row-total");
                    if (totalCell) {
                        totalCell.textContent = totals.hasHoldScore
                            ? totals.totalTreff + "/" + totals.totalInner
                            : "–";
                    }
                    if (isNjff) {
                        var scoreCell = tr.querySelector("td.sa-row-score");
                        if (scoreCell) {
                            scoreCell.textContent = totals.hasHoldScore
                                ? String(totals.totalScore)
                                : "–";
                        }
                    }
                }

                function clampHoldPair(tInput, iInput) {
                    if (!tInput || !iInput) return;
                    var tVal = String(tInput.value || "").trim();
                    var iVal = String(iInput.value || "").trim();
                    tInput.classList.remove("sa-hold-invalid");
                    if (tVal === "" && iVal !== "") {
                        iInput.value = "";
                        iInput.classList.add("sa-hold-invalid");
                        return;
                    }
                    if (tVal !== "" && iVal !== "") {
                        var t = parseInt(tVal, 10);
                        var inn = parseInt(iVal, 10);
                        if (inn > t) {
                            iInput.value = String(t);
                            iInput.classList.remove("sa-hold-invalid");
                        }
                    }
                }

                function sanitizeHoldInput(el) {
                    if (!el || !el.classList.contains("sa-hold-digit")) {
                        return { value: "", shouldAdvance: false };
                    }
                    var isInner = el.getAttribute("data-hold-part") === "i";
                    var raw = String(el.value || "");
                    var v = raw.replace(/[^0-6]/g, "").slice(-1);
                    var shouldAdvance = v.length >= 1;

                    if (!isInner) {
                        el.value = v;
                        el.classList.remove("sa-hold-invalid");
                        var pair = el.closest(".sa-hold-pair-inline") || el.closest(".sa-hold-pair");
                        if (pair) {
                            var inner = pair.querySelector('[data-hold-part="i"]');
                            clampHoldPair(el, inner);
                        }
                    } else {
                        var pairInner = el.closest(".sa-hold-pair-inline") || el.closest(".sa-hold-pair");
                        var treff = pairInner ? pairInner.querySelector('[data-hold-part="t"]') : null;
                        var treffVal = treff ? String(treff.value || "").trim() : "";
                        if (treffVal === "") {
                            el.value = "";
                            el.classList.add("sa-hold-invalid");
                            shouldAdvance = false;
                        } else if (v !== "") {
                            var maxT = parseInt(treffVal, 10);
                            var innN = parseInt(v, 10);
                            if (innN > maxT) {
                                el.value = v;
                                el.classList.add("sa-hold-invalid");
                                shouldAdvance = false;
                            } else {
                                el.value = v;
                                el.classList.remove("sa-hold-invalid");
                            }
                        } else {
                            el.value = v;
                            el.classList.remove("sa-hold-invalid");
                            shouldAdvance = false;
                        }
                    }
                    var tr = el.closest("tr");
                    if (tr) updateRowTotals(tr);
                    return { value: el.value, shouldAdvance: shouldAdvance };
                }

                function sanitizeTbInput(el) {
                    if (!el || !el.classList.contains("sa-tb")) {
                        return { value: "", shouldAdvance: false };
                    }
                    var raw = String(el.value || "");
                    var v = raw.replace(/[^0-9]/g, "").slice(0, 2);
                    if (v !== "") {
                        var n = parseInt(v, 10);
                        if (Number.isFinite(n) && n > 99) {
                            v = "99";
                        }
                    }
                    el.value = v;
                    return { value: v, shouldAdvance: v.length >= 2 };
                }

                function handleFocusChainInput(el) {
                    if (!el || !el.classList.contains("sa-focus-chain") || el.disabled) return;
                    markSlotFormDirty();
                    var result;
                    if (el.classList.contains("sa-tb")) {
                        result = sanitizeTbInput(el);
                    } else {
                        result = sanitizeHoldInput(el);
                    }
                    if (result.shouldAdvance && result.value.length >= 1) {
                        var list = Array.prototype.slice.call(
                            slotModal.querySelectorAll(".sa-focus-chain:not([disabled])")
                        );
                        var ix = list.indexOf(el);
                        if (ix >= 0 && ix + 1 < list.length) {
                            list[ix + 1].focus();
                        }
                    }
                }

                function escHtml(s) {
                    return String(s || "")
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;");
                }

                function printStevneAdminLagSheet(modalEl) {
                    if (!modalEl) return;
                    var sheet = modalEl.querySelector(".sa-print-sheet");
                    if (!sheet) {
                        window.alert("Fant ikke utskriftsinnhold.");
                        return;
                    }
                    var clone = sheet.cloneNode(true);
                    clone.querySelectorAll("select.sa-class-select").forEach(function (sel) {
                        var opt = sel.options[sel.selectedIndex];
                        var span = document.createElement("span");
                        span.textContent = opt ? opt.textContent : "–";
                        sel.parentNode.replaceChild(span, sel);
                    });
                    clone.querySelectorAll("form.sa-roster-remove-form").forEach(function (f) {
                        var span = document.createElement("span");
                        span.textContent = "–";
                        f.parentNode.replaceChild(span, f);
                    });
                    clone.querySelectorAll("button").forEach(function (b) { b.remove(); });
                    clone.querySelectorAll(".sa-screen-only").forEach(function (el) { el.remove(); });
                    clone.querySelectorAll(".sa-col-roster").forEach(function (el) { el.remove(); });

                    function replaceHoldPair(wrap) {
                        var inputs = wrap.querySelectorAll("input.sa-hold-digit");
                        if (inputs.length < 2) return;
                        var t = String(inputs[0].value || "").trim();
                        var inn = String(inputs[1].value || "").trim();
                        var out = document.createElement("span");
                        out.className = "sa-print-hold-pair";
                        out.textContent = (t !== "" ? t : "") + " / " + (inn !== "" ? inn : "");
                        wrap.parentNode.replaceChild(out, wrap);
                    }
                    clone.querySelectorAll(".sa-hold-pair-inline").forEach(replaceHoldPair);
                    clone.querySelectorAll(".sa-hold-pair").forEach(replaceHoldPair);

                    clone.querySelectorAll("input.sa-tb").forEach(function (inp) {
                        var v = String(inp.value || "").trim();
                        var sp = document.createElement("span");
                        sp.className = "sa-print-tb";
                        sp.textContent = v !== "" ? v : "";
                        inp.parentNode.replaceChild(sp, inp);
                    });
                    clone.querySelectorAll("input[type=\"hidden\"]").forEach(function (inp) { inp.remove(); });

                    var printTable = clone.querySelector(".sa-result-table");
                    if (printTable) {
                        printTable.querySelectorAll("tr").forEach(function (tr) {
                            if (tr.cells && tr.cells.length > 0) {
                                tr.deleteCell(0);
                            }
                        });
                    }

                    var stevneTitle = "";
                    var metaLine = "";
                    var lagHeading = "Resultater";
                    var metaRoot = clone.querySelector(".sa-print-sheet-meta");
                    if (metaRoot) {
                        var tEl = metaRoot.querySelector(".sa-print-sheet-title");
                        stevneTitle = tEl ? String(tEl.textContent || "").trim() : "";
                        var subs = metaRoot.querySelectorAll(".sa-print-sheet-sub");
                        var parts = [];
                        for (var si = 0; si < subs.length; si++) {
                            var x = String(subs[si].textContent || "").trim();
                            if (x) parts.push(x);
                        }
                        if (parts.length) {
                            lagHeading = parts[parts.length - 1];
                            parts.pop();
                            metaLine = parts.join(" · ");
                        }
                        metaRoot.remove();
                    }
                    metaLine = (metaLine ? metaLine + " · " : "") + "Utskriftsdato: " + new Date().toLocaleString("nb-NO");
                    var titlePlain = (stevneTitle ? stevneTitle + " – " : "") + lagHeading;
                    var bodyInner = clone.innerHTML;
                    var html =
                        "<!DOCTYPE html><html lang=\"nb\"><head><meta charset=\"utf-8\"><title>" +
                        escHtml(titlePlain) +
                        "</title><style>" +
                        "@page{margin:10mm;}" +
                        "body{font-family:system-ui,Segoe UI,Roboto,sans-serif;color:#111;font-size:12px;margin:0;padding:8px;}" +
                        "h1{font-size:1.25rem;margin:0 0 4px;}" +
                        "h2{font-size:1.1rem;margin:6px 0 4px;font-weight:600;}" +
                        ".meta{color:#444;margin-bottom:6px;font-size:10px;}" +
                        ".hint{margin:0 0 8px;font-size:10px;color:#333;line-height:1.35;}" +
                        ".sa-table-wrap{width:100%;max-width:100%;overflow:visible;}" +
                        ".sa-result-table{width:100%!important;min-width:0!important;max-width:100%;table-layout:fixed;border-collapse:collapse;font-size:11px;}" +
                        ".sa-result-table th,.sa-result-table td{border:1px solid #222;padding:6px 5px;text-align:left;vertical-align:middle;word-wrap:break-word;overflow:hidden;}" +
                        ".sa-result-table thead th{min-height:7.2em;vertical-align:middle;box-sizing:border-box;}" +
                        ".sa-result-table tbody td{min-height:8.25em;vertical-align:middle;box-sizing:border-box;}" +
                        ".sa-result-table th{text-align:center;font-size:10px;line-height:1.25;background:#e8e8e8;font-weight:600;}" +
                        ".sa-result-table .sa-col-figur{width:2.2em;max-width:2.2em;padding:6px 3px;font-size:9px;white-space:nowrap;text-align:center;}" +
                        ".sa-result-table .sa-col-navn{width:14%;max-width:9em;white-space:nowrap;text-align:left;overflow:hidden;text-overflow:ellipsis;}" +
                        ".sa-result-table .sa-col-klasse{width:8%;max-width:5.5em;white-space:nowrap;text-align:left;font-size:10px;overflow:hidden;text-overflow:ellipsis;}" +
                        ".sa-result-table .sa-col-hold{width:auto;min-width:2.6em;white-space:nowrap;text-align:center;vertical-align:middle;}" +
                        ".sa-result-table .sa-col-total{width:4.5em;max-width:4.5em;white-space:normal;line-height:1.2;text-align:center;}" +
                        ".sa-result-table .sa-print-hold-pair,.sa-result-table .sa-print-tb{display:inline-block;text-align:center;font-weight:600;white-space:nowrap;font-size:11px;min-width:2.5em;min-height:1.2em;}" +
                        ".sa-col-roster,.sa-roster-btn,.sa-screen-only{display:none!important;}" +
                        ".footer{margin-top:10px;font-size:9px;color:#666;}" +
                        "</style></head><body>" +
                        "<h1>" + escHtml(stevneTitle) + "</h1>" +
                        '<div class="meta">' + escHtml(metaLine) + "</div>" +
                        "<h2>" + escHtml(lagHeading) + "</h2>" +
                        '<p class="hint">Oversikt for valgt lag. Tomme felt er ment for notering med penn. Verdier som vist i skjema (inkl. ulagrede felt).</p>' +
                        "<div>" + bodyInner + "</div>" +
                        '<p class="footer">StevneAdmin – ark for standplass / dokumentasjon.</p>' +
                        "</body></html>";
                    var w = window.open("", "_blank");
                    if (!w) {
                        window.alert("Kunne ikke åpne utskriftsvindu (popup blokkert?). Tillat popup for denne siden.");
                        return;
                    }
                    w.document.open();
                    w.document.write(html);
                    w.document.close();
                    w.focus();
                    try {
                        w.print();
                    } catch (_) {
                        /* ignore */
                    }
                }

                function markSlotFormDirty() {
                    slotFormDirty = true;
                }

                function clearSlotFormDirty() {
                    slotFormDirty = false;
                }

                function confirmLeaveSlotForm() {
                    return window.confirm("Du har ulagrede endringer på dette laget. Vil du fortsette uten å lagre?");
                }

                function closeSlotModal() {
                    if (slotFormDirty && !confirmLeaveSlotForm()) {
                        return;
                    }
                    clearSlotFormDirty();
                    if (overviewUrl) {
                        window.location.href = overviewUrl;
                    } else if (slotModal) {
                        slotModal.close();
                    }
                }

                if (slotModal && typeof slotModal.showModal === "function") {
                    var saveForm = document.getElementById("sa-save-form");
                    if (saveForm) {
                        slotModal.querySelectorAll(".sa-result-table tbody tr").forEach(function (tr) {
                            updateRowTotals(tr);
                        });
                        saveForm.addEventListener("submit", function () {
                            clearSlotFormDirty();
                        });
                    }

                    slotModal.addEventListener("input", function (e) {
                        var el = e.target;
                        if (!el || !el.classList.contains("sa-focus-chain")) return;
                        handleFocusChainInput(el);
                    });

                    slotModal.addEventListener("keydown", function (e) {
                        var el = e.target;
                        if (!el || !el.classList.contains("sa-focus-chain")) return;
                        if (e.key === "Backspace" && String(el.value || "") === "") {
                            var list = Array.prototype.slice.call(
                                slotModal.querySelectorAll(".sa-focus-chain:not([disabled])")
                            );
                            var ix = list.indexOf(el);
                            if (ix > 0) {
                                e.preventDefault();
                                list[ix - 1].focus();
                            }
                        }
                    });

                    if (slotCloseBtn) {
                        slotCloseBtn.addEventListener("click", closeSlotModal);
                    }
                    slotModal.addEventListener("cancel", function (e) {
                        e.preventDefault();
                        closeSlotModal();
                    });
                    slotModal.addEventListener("click", function (e) {
                        var nav = e.target && e.target.closest ? e.target.closest("a.sa-slot-nav-link") : null;
                        if (nav && slotFormDirty && !confirmLeaveSlotForm()) {
                            e.preventDefault();
                        }
                    });
                    window.addEventListener("beforeunload", function (e) {
                        if (!slotFormDirty) return;
                        e.preventDefault();
                        e.returnValue = "";
                    });

                    var printBtn = document.getElementById("sa-slot-print-btn");
                    if (printBtn) {
                        printBtn.addEventListener("click", function (e) {
                            e.preventDefault();
                            printStevneAdminLagSheet(slotModal);
                        });
                    }

                    slotModal.showModal();
                }

                var rosterCfg = document.getElementById("sa-roster-config");
                var rosterDialog = document.getElementById("sa-roster-assign-dialog");
                var rosterSearch = document.getElementById("sa-roster-search");
                var rosterResults = document.getElementById("sa-roster-search-results");
                var rosterHint = document.getElementById("sa-roster-search-hint");
                var rosterFigureLabel = document.getElementById("sa-roster-figure-label");
                var rosterAssignForm = document.getElementById("sa-roster-assign-form");
                var rosterAssignSlotId = document.getElementById("sa-roster-assign-slot-id");
                var rosterAssignFigure = document.getElementById("sa-roster-assign-figure");
                var rosterAssignParticipantId = document.getElementById("sa-roster-assign-participant-id");
                var rosterCloseBtn = document.getElementById("sa-roster-assign-close");

                if (
                    rosterCfg && rosterDialog && rosterSearch && rosterResults &&
                    rosterAssignForm && rosterAssignSlotId && rosterAssignFigure &&
                    rosterAssignParticipantId && typeof rosterDialog.showModal === "function"
                ) {
                    var rosterSearchUrl = rosterCfg.getAttribute("data-participant-search-url") || "";
                    var rosterTimer = null;

                    function rosterClearResults() {
                        rosterResults.innerHTML = "";
                        if (rosterHint) rosterHint.textContent = "";
                    }

                    function rosterRunSearch() {
                        var q = String(rosterSearch.value || "").trim();
                        if (q.length < 2) {
                            rosterClearResults();
                            if (rosterHint) rosterHint.textContent = "Skriv minst to tegn.";
                            return;
                        }
                        var url = rosterSearchUrl + (rosterSearchUrl.indexOf("?") >= 0 ? "&" : "?") + "q=" + encodeURIComponent(q);
                        fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                rosterClearResults();
                                var items = (data && data.items) || [];
                                if (items.length === 0) {
                                    if (rosterHint) rosterHint.textContent = "Ingen treff.";
                                    return;
                                }
                                if (rosterHint) rosterHint.textContent = "";
                                items.forEach(function (it) {
                                    var id = parseInt(String(it.id || ""), 10);
                                    if (!Number.isFinite(id) || id < 1) return;
                                    var fn = String(it.first_name || "").trim();
                                    var ln = String(it.last_name || "").trim();
                                    var label = (ln + " " + fn).trim() || ("#" + id);
                                    var btn = document.createElement("button");
                                    btn.type = "button";
                                    btn.className = "sa-roster-pick-btn";
                                    btn.textContent = label;
                                    btn.addEventListener("click", function () {
                                        rosterAssignParticipantId.value = String(id);
                                        rosterAssignForm.submit();
                                    });
                                    rosterResults.appendChild(btn);
                                });
                            })
                            .catch(function () {
                                rosterClearResults();
                                if (rosterHint) rosterHint.textContent = "Søket feilet. Prøv igjen.";
                            });
                    }

                    document.querySelectorAll(".sa-roster-add-btn").forEach(function (btn) {
                        btn.addEventListener("click", function () {
                            rosterAssignSlotId.value = btn.getAttribute("data-sa-roster-slot-id") || "";
                            rosterAssignFigure.value = btn.getAttribute("data-sa-roster-figure") || "";
                            rosterAssignParticipantId.value = "";
                            if (rosterFigureLabel) {
                                rosterFigureLabel.textContent = rosterAssignFigure.value || "–";
                            }
                            rosterSearch.value = "";
                            rosterClearResults();
                            rosterDialog.showModal();
                            setTimeout(function () { rosterSearch.focus(); }, 50);
                        });
                    });

                    rosterSearch.addEventListener("input", function () {
                        if (rosterTimer) clearTimeout(rosterTimer);
                        rosterTimer = setTimeout(rosterRunSearch, 320);
                    });

                    if (rosterCloseBtn) {
                        rosterCloseBtn.addEventListener("click", function () { rosterDialog.close(); });
                    }
                }
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
