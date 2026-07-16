<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $organizations */
/** @var int|null $active_organization_id */
/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$activeId = (int) ($active_organization_id ?? 0);
?>
<div class="card">
    <h1>Organisasjoner</h1>
    <p class="muted">Alle organisasjoner du har administratortilgang til. Aktiv organisasjon brukes når du oppretter eller redigerer innhold.</p>

    <?php if ($organizations === []): ?>
        <p>Ingen organisasjoner med administratortilgang.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Navn</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($organizations as $org): ?>
                <?php
                $orgId = (int) ($org['org_id'] ?? 0);
                $isActive = $orgId > 0 && $orgId === $activeId;
                ?>
                <tr>
                    <td>
                        <?= $h((string) ($org['name'] ?? '')) ?>
                        <?php if ($isActive): ?>
                            <span class="muted"> (aktiv)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $h((string) ($org['status'] ?? '')) ?></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="muted">Valgt</span>
                        <?php else: ?>
                            <a class="btn" href="<?= $h($pp::kontekstOrganisasjonBytt()) ?>?organization_id=<?= $orgId ?>">Velg</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
