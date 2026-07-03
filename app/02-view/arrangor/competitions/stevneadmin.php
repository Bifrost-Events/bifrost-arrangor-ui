<?php

declare(strict_types=1);

/** @var int $competition_id */
/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $stevne_admin */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$data = is_array($stevne_admin['data'] ?? null) ? $stevne_admin['data'] : [];
$competition = is_array($data['competition'] ?? null) ? $data['competition'] : [];
$slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];

?>
<h2 style="margin-top:0;">Stevneadmin</h2>
<p class="lead">
    <?= $h((string) ($competition['name'] ?? 'Stevne #' . $competition_id)) ?>
</p>

<?php if (!($stevne_admin['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($stevne_admin['error'] ?? 'Kunne ikke hente stevneadmin.')) ?></p>
<?php else: ?>
    <div class="placeholder-box">
        <p><strong>Grunnleggende stevneadmin</strong></p>
        <p class="muted">Viser data fra backend. Full resultatregistrering bygges i senere iterasjoner.</p>
        <?php if ($slots !== []): ?>
            <p class="muted"><?= count($slots) ?> slot(er) lastet.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p><a href="/stevner/stevneadmin">← Tilbake til oversikt</a></p>
