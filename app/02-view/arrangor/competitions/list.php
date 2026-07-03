<?php

declare(strict_types=1);

/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $competitions */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$items = [];
if (($competitions['ok'] ?? false) && is_array($competitions['data']['competitions'] ?? null)) {
    $items = $competitions['data']['competitions'];
} elseif (($competitions['ok'] ?? false) && is_array($competitions['data'] ?? null)) {
    $items = is_array($competitions['data']['items'] ?? null)
        ? $competitions['data']['items']
        : (array_is_list($competitions['data']) ? $competitions['data'] : []);
}

$canWrite = ($context['can_write'] ?? false) === true;
$roundsById = [];
foreach (is_array($context['rounds'] ?? null) ? $context['rounds'] : [] as $round) {
    if (!is_array($round)) {
        continue;
    }
    $rid = (int) ($round['id'] ?? 0);
    if ($rid > 0) {
        $roundsById[$rid] = $round;
    }
}

?>
<h2 style="margin-top:0;">Mine stevner</h2>
<p class="lead">Stevner for valgt arrangør i gjeldende sesong.</p>

<?php
$organizer_context = $context;
include __DIR__ . '/../_cup-season-context.php';
?>

<div class="toolbar">
    <?php if ($canWrite && $roundsById !== []): ?>
        <a class="btn btn-primary" href="/stevner/ny">Nytt stevne</a>
    <?php elseif ($canWrite): ?>
        <span class="muted">Runder må opprettes i cup-admin før nye stevner kan registreres.</span>
    <?php endif; ?>
</div>

<?php if (!($competitions['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($competitions['error'] ?? 'Kunne ikke hente stevner.')) ?></p>
<?php elseif ($items === []): ?>
    <div class="placeholder-box">
        <p class="muted">Ingen stevner ennå.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Navn</th>
                <th>Runde</th>
                <th>Dato</th>
                <th>Sted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <?php
                $id = (int) ($item['id'] ?? 0);
                $roundId = (int) ($item['round_id'] ?? 0);
                $round = $roundsById[$roundId] ?? null;
                $roundLabel = '–';
                if (is_array($round)) {
                    $roundLabel = 'Runde ' . (int) ($round['round_number'] ?? 0);
                    $roundName = trim((string) ($round['name'] ?? ''));
                    if ($roundName !== '') {
                        $roundLabel .= ' – ' . $roundName;
                    }
                } elseif ($roundId > 0) {
                    $roundLabel = 'Runde #' . $roundId;
                }
                $eventDate = (string) ($item['competition_date'] ?? $item['event_date'] ?? '');
                if ($eventDate !== '' && strtotime($eventDate) !== false) {
                    $eventDate = date('d.m.Y', strtotime($eventDate));
                }
                ?>
                <tr>
                    <td><?= $h((string) ($item['name'] ?? '')) ?></td>
                    <td><?= $h($roundLabel) ?></td>
                    <td><?= $h($eventDate) ?></td>
                    <td><?= $h((string) ($item['location'] ?? '')) ?></td>
                    <td>
                        <a href="/stevner/<?= $id ?>">Rediger</a>
                        ·
                        <a href="/stevner/<?= $id ?>/stevneadmin">Stevneadmin</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
