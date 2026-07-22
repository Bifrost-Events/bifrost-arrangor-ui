<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $organizations */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
?>
<h1>Mine organisasjoner</h1>
<p class="muted">Organisasjoner du eier eller administrerer.</p>
<p><a class="btn" href="<?= $h($pp::mineOrganisasjonerNy()) ?>">Ny organisasjon</a></p>

<?php if ($organizations === []): ?>
    <div class="card">
        <p>Ingen organisasjoner ennå.</p>
        <p><a class="btn" href="<?= $h($pp::mineOrganisasjonerNy()) ?>">Opprett organisasjon</a></p>
    </div>
<?php else: ?>
    <?php foreach ($organizations as $org): ?>
        <div class="card">
            <h2 style="margin:0 0 .35rem;font-size:1.1rem;"><?= $h((string) ($org['name'] ?? '')) ?></h2>
            <?php if (!empty($org['organization_number'])): ?>
                <p class="muted" style="margin:0;">Org.nr. <?= $h((string) $org['organization_number']) ?></p>
            <?php endif; ?>
            <?php if (!empty($org['roles']) && is_array($org['roles'])): ?>
                <p class="muted" style="margin:.35rem 0 0;">
                    Roller: <?= $h(implode(', ', array_map('strval', $org['roles']))) ?>
                </p>
            <?php elseif (!empty($org['role'])): ?>
                <p class="muted" style="margin:.35rem 0 0;">Rolle: <?= $h((string) $org['role']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
