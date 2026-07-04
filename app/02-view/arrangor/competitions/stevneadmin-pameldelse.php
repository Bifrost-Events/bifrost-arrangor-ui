<?php

declare(strict_types=1);

/** @var int $competition_id */
/** @var array<string, mixed>|null $pameldelse_data */
/** @var list<array<string, mixed>> $participants */
/** @var array<string, mixed> $context */
/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $roster */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$canWrite = (bool) ($context['can_write'] ?? false);
$data = is_array($pameldelse_data) ? $pameldelse_data : [];
$slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];
$figuresPerSlot = max(1, (int) ($data['figures_per_slot'] ?? 6));
$reservedSet = is_array($data['reserved_set'] ?? null) ? $data['reserved_set'] : [];
$occupantByKey = is_array($data['occupant_by_key'] ?? null) ? $data['occupant_by_key'] : [];

?>
<style>
    .pm-grid-wrap { overflow-x: auto; margin-top: 1rem; }
    .pm-grid { border-collapse: collapse; min-width: 640px; width: 100%; }
    .pm-grid th, .pm-grid td { border: 1px solid var(--line); padding: 0.4rem 0.5rem; text-align: center; vertical-align: middle; font-size: 0.88rem; }
    .pm-grid th { background: #f4f7f4; color: var(--muted); font-size: 0.8rem; }
    .pm-grid .pm-lag-head { text-align: left; white-space: nowrap; }
    .pm-cell-ledig { background: #e6f2e8; }
    .pm-cell-opptatt { background: #fff; }
    .pm-cell-reservert { background: #f3ebe6; color: #6b4226; }
    .pm-cell-actions { display: flex; flex-direction: column; gap: 0.25rem; align-items: stretch; }
    .pm-cell-actions form { margin: 0; }
    .pm-cell-actions .btn { padding: 0.2rem 0.4rem; font-size: 0.75rem; width: 100%; }
    .pm-assign-dialog { border: 1px solid var(--line); border-radius: 6px; padding: 1rem; max-width: 420px; }
    .pm-assign-dialog::backdrop { background: rgba(0,0,0,0.35); }
</style>

<?php if (!($roster['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($roster['error'] ?? 'Kunne ikke hente påmeldingsdata.')) ?></p>
<?php elseif ($slots === []): ?>
    <div class="placeholder-box">
        <p class="muted">Ingen lag er opprettet ennå.</p>
        <?php if ($canWrite): ?>
            <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/generer-lag" style="margin-top:0.75rem;">
                <button type="submit" class="btn btn-primary">Generer lag og skiver</button>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <p class="lead" style="margin-top:0;">Meld på deltakere, reserver lag eller enkeltskiver.</p>
    <p class="muted">Trykk «Meld på» på ledige skiver, eller reserver plasser for arrangør.</p>

    <div class="pm-grid-wrap">
        <table class="pm-grid">
            <thead>
                <tr>
                    <th>Lag</th>
                    <th>Tid</th>
                    <?php for ($f = 1; $f <= $figuresPerSlot; $f++): ?>
                        <th>Skive <?= $f ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $slot): ?>
                    <?php
                    if (!is_array($slot)) {
                        continue;
                    }
                    $sid = (int) ($slot['id'] ?? 0);
                    $sn = (int) ($slot['slot_number'] ?? 0);
                    $wholeReserved = !empty($slot['is_reserved']);
                    ?>
                    <tr>
                        <td class="pm-lag-head">
                            <strong>Lag <?= $sn ?></strong>
                            <?php if ($canWrite): ?>
                                <div class="pm-cell-actions" style="margin-top:0.35rem;">
                                    <?php if ($wholeReserved): ?>
                                        <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/reserver-lag">
                                            <input type="hidden" name="slot_id" value="<?= $sid ?>">
                                            <button type="submit" name="unreserve" value="1" class="btn">Frigi lag</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/reserver-lag">
                                            <input type="hidden" name="slot_id" value="<?= $sid ?>">
                                            <button type="submit" name="reserve" value="1" class="btn">Reserver lag</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($wholeReserved): ?>
                                <br><span class="muted">Reservert</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $h((string) ($slot['start_time'] ?? '–')) ?></td>
                        <?php for ($f = 1; $f <= $figuresPerSlot; $f++):
                            $figKey = $sid . '_' . $f;
                            $resKey = $sn . '_' . $f;
                            $occ = $occupantByKey[$figKey] ?? null;
                            $isFigReserved = isset($reservedSet[$resKey]);
                            $cellClass = 'pm-cell-opptatt';
                            if ($wholeReserved || $isFigReserved) {
                                $cellClass = 'pm-cell-reservert';
                            } elseif ($occ === null) {
                                $cellClass = 'pm-cell-ledig';
                            }
                            ?>
                            <td class="<?= $cellClass ?>">
                                <?php if ($wholeReserved): ?>
                                    <span class="muted">–</span>
                                <?php elseif ($occ !== null): ?>
                                    <strong><?= $h((string) ($occ['name'] ?? '')) ?></strong>
                                    <?php if ($canWrite): ?>
                                        <div class="pm-cell-actions" style="margin-top:0.35rem;">
                                            <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/lag/<?= $sn ?>/fjern" onsubmit="return confirm('Fjerne deltaker fra skiven?');">
                                                <input type="hidden" name="figure_number" value="<?= $f ?>">
                                                <button type="submit" class="btn">Fjern</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($isFigReserved): ?>
                                    Reservert
                                    <?php if ($canWrite): ?>
                                        <div class="pm-cell-actions" style="margin-top:0.35rem;">
                                            <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/reserver-skive">
                                                <input type="hidden" name="slot_id" value="<?= $sid ?>">
                                                <input type="hidden" name="figure_number" value="<?= $f ?>">
                                                <button type="submit" name="unreserve" value="1" class="btn">Frigi</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($canWrite): ?>
                                    <div class="pm-cell-actions">
                                        <button type="button" class="btn btn-primary pm-open-assign"
                                                data-slot-id="<?= $sid ?>"
                                                data-figure="<?= $f ?>"
                                                data-label="Lag <?= $sn ?>, skive <?= $f ?>">Meld på</button>
                                        <form method="post" action="/stevner/<?= $competition_id ?>/stevneadmin/reserver-skive">
                                            <input type="hidden" name="slot_id" value="<?= $sid ?>">
                                            <input type="hidden" name="figure_number" value="<?= $f ?>">
                                            <button type="submit" name="reserve" value="1" class="btn">Reserver</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    Ledig
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canWrite): ?>
    <dialog id="pm-assign-dialog" class="pm-assign-dialog">
        <form method="post" id="pm-assign-form" action="">
            <h3 style="margin-top:0;">Meld på deltaker</h3>
            <p id="pm-assign-label" class="muted"></p>
            <input type="hidden" name="slot_id" id="pm-assign-slot-id">
            <input type="hidden" name="figure_number" id="pm-assign-figure">
            <div style="margin:0.75rem 0;">
                <label for="pm-assign-participant" style="font-weight:600;display:block;margin-bottom:0.25rem;">Deltaker</label>
                <select name="participant_id" id="pm-assign-participant" required style="width:100%;padding:0.4rem;">
                    <option value="">Velg deltaker</option>
                    <?php foreach ($participants as $p): ?>
                        <?php if (!is_array($p)) {
                            continue;
                        } ?>
                        <?php
                        $pid = (int) ($p['id'] ?? 0);
                        if ($pid < 1) {
                            continue;
                        }
                        $label = trim((string) ($p['first_name'] ?? '') . ' ' . (string) ($p['last_name'] ?? ''));
                        ?>
                        <option value="<?= $pid ?>"><?= $h($label !== '' ? $label : 'Deltaker #' . $pid) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar" style="margin:0;">
                <button type="submit" class="btn btn-primary">Meld på</button>
                <button type="button" class="btn" id="pm-assign-cancel">Avbryt</button>
            </div>
        </form>
    </dialog>
    <script>
    (function() {
        var dialog = document.getElementById('pm-assign-dialog');
        var form = document.getElementById('pm-assign-form');
        var label = document.getElementById('pm-assign-label');
        var slotInput = document.getElementById('pm-assign-slot-id');
        var figureInput = document.getElementById('pm-assign-figure');
        var cancelBtn = document.getElementById('pm-assign-cancel');
        if (!dialog || !form) return;

        document.querySelectorAll('.pm-open-assign').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var slotId = btn.getAttribute('data-slot-id') || '';
                var figure = btn.getAttribute('data-figure') || '';
                var slotLabel = btn.getAttribute('data-label') || '';
                var slotNumber = slotLabel.match(/Lag (\d+)/);
                slotNumber = slotNumber ? slotNumber[1] : '0';
                form.action = '/stevner/<?= $competition_id ?>/stevneadmin/lag/' + slotNumber + '/tilordne';
                slotInput.value = slotId;
                figureInput.value = figure;
                label.textContent = slotLabel;
                dialog.showModal();
            });
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() { dialog.close(); });
        }
    })();
    </script>
    <?php endif; ?>
<?php endif; ?>
