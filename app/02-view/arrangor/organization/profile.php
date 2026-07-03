<?php

declare(strict_types=1);

/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$org = is_array($context['selected_organization'] ?? null) ? $context['selected_organization'] : null;
$approval = is_array($context['approval'] ?? null) ? $context['approval'] : null;

?>
<h2 style="margin-top:0;">Min organisasjon</h2>
<p class="lead">Profil og sesongstatus for valgt arrangør.</p>

<?php if ($org === null): ?>
    <div class="placeholder-box">
        <p class="muted">Du er ikke knyttet til en arrangør ennå.</p>
        <p><a class="btn btn-primary" href="/bli-arrangor">Bli arrangør</a></p>
    </div>
<?php else: ?>
    <table>
        <tbody>
            <tr><th>Navn</th><td><?= $h((string) ($org['name'] ?? '')) ?></td></tr>
            <tr><th>E-post</th><td><?= $h((string) ($org['contact_email'] ?? '')) ?></td></tr>
            <tr><th>Telefon</th><td><?= $h((string) ($org['contact_phone'] ?? '')) ?></td></tr>
            <tr><th>Postnummer</th><td><?= $h((string) ($org['postal_code'] ?? '')) ?></td></tr>
            <tr><th>Poststed</th><td><?= $h((string) ($org['city'] ?? '')) ?></td></tr>
        </tbody>
    </table>

    <?php if ($approval !== null): ?>
        <p style="margin-top:1rem;">
            Sesongstatus:
            <span class="badge badge-<?= ($approval['status'] ?? '') === 'approved' ? 'ok' : 'pending' ?>">
                <?= $h((string) ($approval['status'] ?? 'ukjent')) ?>
            </span>
        </p>
    <?php endif; ?>

    <p class="toolbar"><a class="btn" href="/organisasjon/medlemmer">Medlemmer og invitasjoner</a></p>
<?php endif; ?>
