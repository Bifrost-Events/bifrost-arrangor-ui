<?php

declare(strict_types=1);

/** @var array<string, string> $form */
/** @var string $error */
/** @var array{host: string, resolved: bool, error: string|null, tenant: array<string, mixed>|null, tenant_id: int, display_name: string} $tenant_context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';
$tenantContext = is_array($tenant_context ?? null) ? $tenant_context : ['resolved' => false, 'display_name' => 'Cup', 'host' => ''];
$canSubmit = ($tenantContext['resolved'] ?? false) === true;

?>
<h2 style="margin-top:0;">Opprett arrangør</h2>
<p class="lead">
    Registrer arrangørorganisasjonen din for
    <strong><?= $h((string) ($tenantContext['display_name'] ?? 'cup')) ?></strong>.
    Søknaden må godkjennes for inneværende sesong.
</p>

<?php if (!$canSubmit): ?>
    <p class="form-error" role="alert">
        <?= $h($error !== '' ? $error : 'Kunne ikke finne cup for dette domenet.') ?>
    </p>
    <p class="muted">
        Cupen bestemmes av domenet du besøker
        (<code><?= $h((string) ($tenantContext['host'] ?? '')) ?></code>).
        Konfigurer arrangør-domene i admin-portalen under cup → domener.
    </p>
<?php else: ?>
    <?php if ($error !== ''): ?>
        <p class="form-error" role="alert"><?= $h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/bli-arrangor/opprett" class="form-grid">
        <div>
            <label for="name">Arrangørnavn *</label>
            <input type="text" id="name" name="name" value="<?= $h((string) ($form['name'] ?? '')) ?>" required>
        </div>
        <div>
            <label for="contact_email">Kontakt e-post</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= $h((string) ($form['contact_email'] ?? '')) ?>">
        </div>
        <div>
            <label for="contact_phone">Telefon</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?= $h((string) ($form['contact_phone'] ?? '')) ?>">
        </div>
        <div>
            <label for="postal_code">Postnummer</label>
            <input type="text" id="postal_code" name="postal_code" value="<?= $h((string) ($form['postal_code'] ?? '')) ?>">
        </div>
        <div>
            <label for="city">Poststed</label>
            <input type="text" id="city" name="city" value="<?= $h((string) ($form['city'] ?? '')) ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;">Opprett arrangør</button>
    </form>
<?php endif; ?>
