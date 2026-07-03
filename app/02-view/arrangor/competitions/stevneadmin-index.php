<?php

declare(strict_types=1);

/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $competitions */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$items = [];
if (($competitions['ok'] ?? false) && is_array($competitions['data']['competitions'] ?? null)) {
    $items = $competitions['data']['competitions'];
}

?>
<h2 style="margin-top:0;">Stevneadmin</h2>
<p class="lead">Velg et stevne for resultater og godkjenning.</p>

<?php if (!($competitions['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($competitions['error'] ?? 'Kunne ikke hente stevner.')) ?></p>
<?php elseif ($items === []): ?>
    <div class="placeholder-box"><p class="muted">Ingen stevner tilgjengelig.</p></div>
<?php else: ?>
    <table>
        <thead><tr><th>Navn</th><th>Dato</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <?php $id = (int) ($item['id'] ?? 0); ?>
                <tr>
                    <td><?= $h((string) ($item['name'] ?? '')) ?></td>
                    <td><?= $h((string) ($item['event_date'] ?? '')) ?></td>
                    <td><a href="/stevner/<?= $id ?>/stevneadmin">Åpne stevneadmin</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
