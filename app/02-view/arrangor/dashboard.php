<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $user */
/** @var array{ok: bool, status: int, data: array<string, mixed>|null, error: string|null} $health */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$approval = is_array($context['approval'] ?? null) ? $context['approval'] : null;
$approvalStatus = (string) ($approval['status'] ?? '');
$selectedOrg = is_array($context['selected_organization'] ?? null) ? $context['selected_organization'] : null;
$backendOk = ($health['ok'] ?? false) === true;

?>
<h2 style="margin-top:0;">Oversikt</h2>
<p class="lead">Velkommen til arrangørportalen. Her administrerer du stevner, deltakere og organisasjonen din.</p>

<div class="toolbar">
    <span>Backend:</span>
    <span class="badge <?= $backendOk ? 'badge-ok' : 'badge-bad' ?>"><?= $backendOk ? 'OK' : 'Feil' ?></span>
    <?php if ($selectedOrg !== null): ?>
        <span class="muted">Arrangør: <strong><?= $h((string) ($selectedOrg['name'] ?? '')) ?></strong></span>
    <?php else: ?>
        <a class="btn btn-primary" href="/bli-arrangor">Bli arrangør</a>
    <?php endif; ?>
</div>

<?php if ($approval !== null): ?>
    <p>
        Sesongstatus:
        <?php if ($approvalStatus === 'approved'): ?>
            <span class="badge badge-ok">Godkjent</span>
        <?php elseif ($approvalStatus === 'pending'): ?>
            <span class="badge badge-pending">Venter på godkjenning</span>
        <?php else: ?>
            <span class="badge badge-bad"><?= $h($approvalStatus !== '' ? $approvalStatus : 'Ukjent') ?></span>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if (($context['can_write'] ?? false) !== true && $selectedOrg !== null): ?>
    <div class="placeholder-box">
        <p><strong>Begrenset tilgang</strong></p>
        <p class="muted">Du kan se informasjon, men ikke endre stevner før arrangøren er godkjent for sesongen.</p>
    </div>
<?php endif; ?>
