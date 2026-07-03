<?php

declare(strict_types=1);

/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $participants */
/** @var array<string, mixed> $context */
/** @var string $error */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';

$items = [];
if (($participants['ok'] ?? false) && is_array($participants['data']['participants'] ?? null)) {
    $items = $participants['data']['participants'];
}

$canWrite = ($context['can_write'] ?? false) === true;

?>
<h2 style="margin-top:0;">Deltakerliste</h2>
<p class="lead">Skyttere og deltakere knyttet til arrangøren.</p>

<?php if ($error !== ''): ?>
    <p class="form-error" role="alert"><?= $h($error) ?></p>
<?php endif; ?>

<?php if (!($participants['ok'] ?? false)): ?>
    <p class="form-error"><?= $h((string) ($participants['error'] ?? 'Kunne ikke hente deltakere.')) ?></p>
<?php elseif ($items === []): ?>
    <div class="placeholder-box"><p class="muted">Ingen deltakere registrert.</p></div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Navn</th>
                <th>E-post</th>
                <th>Telefon</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <tr>
                    <td><?= $h(trim((string) ($item['first_name'] ?? '') . ' ' . (string) ($item['last_name'] ?? ''))) ?></td>
                    <td><?= $h((string) ($item['email'] ?? '')) ?></td>
                    <td><?= $h((string) ($item['phone'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($canWrite): ?>
    <h3>Ny deltaker</h3>
    <form method="post" action="/deltakere" class="form-grid">
        <div>
            <label for="first_name">Fornavn</label>
            <input type="text" id="first_name" name="first_name">
        </div>
        <div>
            <label for="last_name">Etternavn</label>
            <input type="text" id="last_name" name="last_name">
        </div>
        <div>
            <label for="email">E-post</label>
            <input type="email" id="email" name="email">
        </div>
        <div>
            <label for="phone">Telefon</label>
            <input type="text" id="phone" name="phone">
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;">Legg til</button>
    </form>
<?php endif; ?>
