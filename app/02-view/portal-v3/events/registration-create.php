<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $space */
/** @var array<string, mixed> $event */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var list<array<string, mixed>> $candidates */
/** @var array<string, mixed> $form */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$eventId = (int) ($event['event_id'] ?? 0);
?>
<p>
    <a href="<?= $h($pp::stevnePameldinger($eventId)) ?>">← Påmeldinger</a>
</p>
<h1>Manuell påmelding</h1>
<p class="muted"><?= $h($labels->singular('event')) ?>: <?= $h((string) ($event['name'] ?? '')) ?></p>

<?php if ($candidates !== []): ?>
    <div class="card" style="margin-bottom:1rem; border-left:4px solid #b7791f;">
        <p><strong>Mulige eksisterende personer funnet</strong> — ingen automatisk sammenslåing.</p>
        <ul>
            <?php foreach ($candidates as $c): ?>
                <li>
                    #<?= (int) ($c['person_id'] ?? 0) ?>
                    <?= $h((string) ($c['display_name'] ?? '')) ?>
                    <?php if (!empty($c['email'])): ?> · <?= $h((string) $c['email']) ?><?php endif; ?>
                    <?php if (!empty($c['phone'])): ?> · <?= $h((string) $c['phone']) ?><?php endif; ?>
                    (<?= $h((string) ($c['match_reason'] ?? '')) ?>)
                    — bruk person_id <?= (int) ($c['person_id'] ?? 0) ?> under, eller bekreft ny oppretting.
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= $h($pp::stevnePameldinger($eventId)) ?>" class="card" style="max-width:36rem; display:grid; gap:.75rem;">
    <h2 style="margin:0; font-size:1.05rem;">Eksisterende person</h2>
    <label for="person_id">Person-ID</label>
    <input type="number" id="person_id" name="person_id" min="1" value="<?= $h((string) ($form['person_id'] ?? '')) ?>">

    <h2 style="margin:.5rem 0 0; font-size:1.05rem;">Eller ny person</h2>
    <p class="muted" style="margin:0;">Oppretter ikke brukerkonto eller representasjon.</p>
    <label for="first_name">Fornavn</label>
    <input type="text" id="first_name" name="first_name" value="<?= $h((string) ($form['first_name'] ?? '')) ?>">
    <label for="last_name">Etternavn</label>
    <input type="text" id="last_name" name="last_name" value="<?= $h((string) ($form['last_name'] ?? '')) ?>">
    <label for="birth_date">Fødselsdato</label>
    <input type="date" id="birth_date" name="birth_date" value="<?= $h((string) ($form['birth_date'] ?? '')) ?>">
    <label for="email">E-post</label>
    <input type="email" id="email" name="email" value="<?= $h((string) ($form['email'] ?? '')) ?>">
    <label for="phone">Telefon</label>
    <input type="text" id="phone" name="phone" value="<?= $h((string) ($form['phone'] ?? '')) ?>">

    <label for="notes">Notater</label>
    <textarea id="notes" name="notes" rows="2"><?= $h((string) ($form['notes'] ?? '')) ?></textarea>

    <label><input type="checkbox" name="force_capacity_override" value="1" <?= !empty($form['force_capacity_override']) ? 'checked' : '' ?>> Overstyr kapasitet</label>
    <?php if ($candidates !== []): ?>
        <label><input type="checkbox" name="confirm_create" value="1"> Opprett ny person likevel</label>
    <?php endif; ?>

    <p><button type="submit" class="btn">Meld på</button></p>
</form>
