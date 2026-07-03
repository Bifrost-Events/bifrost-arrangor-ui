<?php

declare(strict_types=1);

/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $members */
/** @var array<string, mixed> $context */
/** @var string $invite_email */
/** @var string $error */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';

$items = [];
if (($members['ok'] ?? false) && is_array($members['data']['members'] ?? null)) {
    $items = $members['data']['members'];
}

$canWrite = ($context['can_write'] ?? false) === true;

?>
<h2 style="margin-top:0;">Medlemmer</h2>
<p class="lead">Brukere med tilgang til arrangørorganisasjonen.</p>

<?php if ($error !== ''): ?>
    <p class="form-error" role="alert"><?= $h($error) ?></p>
<?php endif; ?>

<?php if (!($members['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($members['error'] ?? 'Kunne ikke hente medlemmer.')) ?></p>
<?php elseif ($items === []): ?>
    <div class="placeholder-box"><p class="muted">Ingen medlemmer funnet.</p></div>
<?php else: ?>
    <table>
        <thead><tr><th>Navn</th><th>E-post</th><th>Rolle</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <tr>
                    <td><?= $h((string) ($item['name'] ?? $item['user_name'] ?? '')) ?></td>
                    <td><?= $h((string) ($item['email'] ?? '')) ?></td>
                    <td><?= $h((string) ($item['role'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($canWrite): ?>
    <h3>Inviter medlem</h3>
    <form method="post" action="/organisasjon/medlemmer/inviter" class="form-grid">
        <div>
            <label for="email">E-post</label>
            <input type="email" id="email" name="email" value="<?= $h((string) ($invite_email ?? '')) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;">Send invitasjon</button>
    </form>
<?php endif; ?>
