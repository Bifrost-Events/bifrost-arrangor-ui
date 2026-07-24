<?php

declare(strict_types=1);

/** @var string $wizard_step */
/** @var list<array{key: string, label: string, status: string}> $wizard_steps */
/** @var bool $needs_account */
/** @var array<string, mixed> $form */
/** @var array<string, string> $errors */
/** @var list<array<string, mixed>> $organizations */
/** @var list<array<string, mixed>> $available_series */
/** @var list<array<string, mixed>> $approved_series */
/** @var list<array{application_id: int, application_name: string}> $application_options */
/** @var bool $domain_bound */
/** @var string $domain_application_name */
/** @var int|null $onboarding_org_id */
/** @var int|null $onboarding_application_id */
/** @var int|null $onboarding_series_id */
/** @var array<string, mixed>|null $selected_series */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pp = $pp ?? \App\Support\PortalPaths::class;
$step = (string) ($wizard_step ?? 'account');
$wizardSteps = is_array($wizard_steps ?? null) ? $wizard_steps : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$organizations = is_array($organizations ?? null) ? $organizations : [];
$availableSeries = is_array($available_series ?? null) ? $available_series : [];
$approvedSeries = is_array($approved_series ?? null) ? $approved_series : [];
$applicationOptions = is_array($application_options ?? null) ? $application_options : [];
$domainBound = (bool) ($domain_bound ?? false);
$domainAppName = (string) ($domain_application_name ?? '');
$onboardingOrgId = (int) ($onboarding_org_id ?? 0);
$selectedSeries = is_array($selected_series ?? null) ? $selected_series : null;
$openSeries = array_values(array_filter(
    $availableSeries,
    static fn (array $s): bool => !empty($s['is_accepting']),
));
$seasonLabel = static function (array $series): string {
    $label = (string) ($series['name'] ?? '');
    if (!empty($series['season_label'])) {
        $label .= ' (' . $series['season_label'] . ')';
    }

    return $label;
};
?>
<h1>Kom i gang</h1>
<p class="muted">
    Steg for steg: brukerkonto → arrangørprofil → søknad om arrangørtilgang
    <?= $domainBound && $domainAppName !== '' ? ' i ' . $h($domainAppName) : '' ?>.
</p>

<?php if ($wizardSteps !== []): ?>
<nav class="card" aria-label="Fremdrift" style="padding:.75rem 1rem;">
    <ol style="display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;margin:0;padding:0;list-style:none;">
        <?php foreach ($wizardSteps as $i => $ws): ?>
            <?php
            $status = (string) ($ws['status'] ?? 'upcoming');
            $label = (string) ($ws['label'] ?? '');
            $key = (string) ($ws['key'] ?? '');
            $weight = $status === 'current' ? '700' : '500';
            $color = match ($status) {
                'done' => '#2c5530',
                'current' => '#1a1a18',
                default => '#8a8a86',
            };
            ?>
            <li style="font-weight:<?= $weight ?>;color:<?= $color ?>;">
                <?php if ($status === 'done'): ?>
                    <a href="<?= $h($pp::komIGang() . '?step=' . rawurlencode($key)) ?>" style="color:inherit;">
                        <?= (int) ($i + 1) ?>. <?= $h($label) ?> ✓
                    </a>
                <?php else: ?>
                    <?= (int) ($i + 1) ?>. <?= $h($label) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="card" style="border-color:#f0c4c4;background:#fdeaea;">
        <ul style="margin:0;color:#9b2c2c;">
            <?php foreach ($errors as $field => $msg): ?>
                <li><?= $h(is_string($field) ? $field . ': ' : '') ?><?= $h((string) $msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($step === 'account'): ?>
<div class="card" style="max-width:28rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Opprett brukerkonto</h2>
    <p class="muted">
        En vanlig Bifrost-brukerkonto for innlogging. Arrangørprofil og arrangørtilgang til cuper kommer i neste steg.
    </p>
    <form method="post" action="<?= $h($pp::komIGang()) ?>">
        <input type="hidden" name="wizard_step" value="account">
        <label for="first_name">Fornavn</label>
        <input type="text" id="first_name" name="first_name" required autocomplete="given-name"
               value="<?= $h((string) ($form['first_name'] ?? '')) ?>">

        <label for="last_name">Etternavn</label>
        <input type="text" id="last_name" name="last_name" required autocomplete="family-name"
               value="<?= $h((string) ($form['last_name'] ?? '')) ?>">

        <label for="email">E-post</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               value="<?= $h((string) ($form['email'] ?? '')) ?>">

        <label for="phone">Telefon</label>
        <input type="tel" id="phone" name="phone" autocomplete="tel"
               value="<?= $h((string) ($form['phone'] ?? '')) ?>">

        <label for="password">Passord</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

        <label for="password_confirm">Bekreft passord</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">

        <p style="margin-top:1rem;">
            <button type="submit" class="btn">Opprett brukerkonto og fortsett</button>
        </p>
    </form>
    <p class="muted" style="margin-top:1rem;">
        Har du allerede brukerkonto? <a href="<?= $h($pp::login()) ?>">Logg inn</a>
    </p>
</div>

<?php elseif ($step === 'organization'): ?>
<div class="card" style="max-width:32rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Organisasjon</h2>
    <?php if ($organizations !== []): ?>
        <p class="muted">Velg organisasjonen du søker på vegne av, eller opprett en ny.</p>
        <form method="post" action="<?= $h($pp::komIGang()) ?>" style="margin-bottom:1.5rem;">
            <input type="hidden" name="wizard_step" value="organization">
            <input type="hidden" name="action" value="select">
            <label for="org_id">Eksisterende organisasjon</label>
            <select id="org_id" name="org_id" required>
                <?php foreach ($organizations as $org): ?>
                    <?php $oid = (int) ($org['org_id'] ?? 0); ?>
                    <option value="<?= $oid ?>" <?= $oid === $onboardingOrgId ? 'selected' : '' ?>>
                        <?= $h((string) ($org['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="margin-top:1rem;">
                <button type="submit" class="btn">Fortsett med valgt organisasjon</button>
            </p>
        </form>
        <hr style="border:0;border-top:1px solid #d8d8d4;margin:1.25rem 0;">
        <h3 style="font-size:1rem;margin:0 0 .5rem;">Eller opprett ny</h3>
    <?php else: ?>
        <p class="muted">Opprett organisasjonen du skal arrangere for. Du blir eier.</p>
    <?php endif; ?>

    <form method="post" action="<?= $h($pp::komIGang()) ?>">
        <input type="hidden" name="wizard_step" value="organization">
        <input type="hidden" name="action" value="create">
        <label for="name">Navn *</label>
        <input type="text" id="name" name="name" required value="<?= $h((string) ($form['name'] ?? '')) ?>">

        <label for="organization_number">Organisasjonsnummer</label>
        <input type="text" id="organization_number" name="organization_number"
               value="<?= $h((string) ($form['organization_number'] ?? '')) ?>">

        <label for="email_org">E-post</label>
        <input type="email" id="email_org" name="email" value="<?= $h((string) ($form['email'] ?? '')) ?>">

        <label for="phone_org">Telefon</label>
        <input type="tel" id="phone_org" name="phone" value="<?= $h((string) ($form['phone'] ?? '')) ?>">

        <p style="margin-top:1rem;">
            <button type="submit" class="btn">Opprett organisasjon og fortsett</button>
        </p>
    </form>
</div>

<?php elseif ($step === 'application'): ?>
<div class="card" style="max-width:28rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Velg cup</h2>
    <p class="muted">Portalen er ikke bundet til én cup. Velg hvilken cup du vil søke om å arrangere i.</p>
    <?php if ($applicationOptions === []): ?>
        <p>Ingen cuper tar imot arrangørsøknader for øyeblikket.</p>
    <?php else: ?>
        <form method="post" action="<?= $h($pp::komIGang()) ?>">
            <input type="hidden" name="wizard_step" value="application">
            <label for="application_id">Cup / applikasjon *</label>
            <select id="application_id" name="application_id" required>
                <option value="">Velg…</option>
                <?php foreach ($applicationOptions as $opt): ?>
                    <?php $aid = (int) ($opt['application_id'] ?? 0); ?>
                    <option value="<?= $aid ?>" <?= $aid === (int) ($onboarding_application_id ?? 0) ? 'selected' : '' ?>>
                        <?= $h((string) ($opt['application_name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p style="margin-top:1rem;">
                <button type="submit" class="btn">Fortsett</button>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php elseif ($step === 'series'): ?>
<div class="card" style="max-width:32rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Velg sesong</h2>
    <?php if ($domainBound && $domainAppName !== ''): ?>
        <p class="muted">Åpne sesonger i <?= $h($domainAppName) ?>.</p>
    <?php else: ?>
        <p class="muted">Velg sesongen du vil søke om å arrangere i.</p>
    <?php endif; ?>

    <?php if ($approvedSeries !== []): ?>
        <div style="margin:1rem 0;padding:.75rem 1rem;background:#f4f6f4;border-radius:6px;">
            <p style="margin:0 0 .5rem;font-weight:600;font-size:.9rem;">Allerede godkjent arrangør</p>
            <ul style="margin:0;padding-left:1.1rem;">
                <?php foreach ($approvedSeries as $approved): ?>
                    <li style="margin:.25rem 0;">
                        <?= $h($seasonLabel($approved)) ?>
                        <span class="muted" style="font-size:.85rem;"> · godkjent</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p style="margin:.75rem 0 0;">
                <a class="btn" href="<?= $h($pp::stevner() . '?season_scope=all') ?>">Gå til mine stevner</a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($openSeries === []): ?>
        <p><?= $approvedSeries !== []
            ? 'Ingen flere sesonger tar imot søknader her nå.'
            : 'Ingen sesonger tar imot søknader her nå.' ?></p>
        <?php if (!$domainBound): ?>
            <p><a href="<?= $h($pp::komIGang() . '?step=application') ?>">← Velg en annen cup</a></p>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="<?= $h($pp::komIGang()) ?>">
            <input type="hidden" name="wizard_step" value="series">
            <fieldset style="border:0;margin:0;padding:0;">
                <legend style="font-weight:600;margin-bottom:.5rem;">Sesong *</legend>
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem;">
                    <?php foreach ($openSeries as $series): ?>
                        <?php
                        $sid = (int) ($series['series_id'] ?? 0);
                        $checked = $sid === (int) ($onboarding_series_id ?? 0);
                        $existing = is_array($series['existing_application'] ?? null)
                            ? $series['existing_application']
                            : null;
                        ?>
                        <li>
                            <label style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;padding:.55rem .7rem;border:1px solid #d8d8d4;border-radius:6px;">
                                <input type="radio" name="series_id" value="<?= $sid ?>" required
                                       <?= $checked ? 'checked' : '' ?>
                                       style="margin-top:.2rem;">
                                <span>
                                    <span style="font-weight:600;"><?= $h($seasonLabel($series)) ?></span>
                                    <?php if ($existing !== null): ?>
                                        <span class="muted" style="display:block;font-size:.85rem;">
                                            Har allerede søknad (<?= $h((string) ($existing['application_status'] ?? '')) ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </fieldset>
            <p style="margin-top:1rem;">
                <button type="submit" class="btn">Fortsett til søknad</button>
            </p>
        </form>
    <?php endif; ?>
</div>

<?php elseif ($step === 'details'): ?>
<div class="card" style="max-width:32rem;">
    <h2 style="margin-top:0;font-size:1.05rem;">Søknad om å bli arrangør</h2>
    <?php if ($selectedSeries !== null): ?>
        <p class="muted" style="margin-bottom:1rem;">
            Sesong: <strong><?= $h((string) ($selectedSeries['name'] ?? '')) ?></strong>
            <?php if (!empty($selectedSeries['space_name'])): ?>
                · <?= $h((string) $selectedSeries['space_name']) ?>
            <?php endif; ?>
            · <a href="<?= $h($pp::komIGang() . '?step=series') ?>">Bytt</a>
        </p>
    <?php endif; ?>

    <p style="margin-bottom:1rem;">
        Søknaden gjelder <strong>hele sesongen</strong>. Når den er godkjent kan organisasjonen opprette stevner fritt i sesongen.
    </p>

    <form method="post" action="<?= $h($pp::komIGang()) ?>">
        <input type="hidden" name="wizard_step" value="details">

        <label for="message">Melding til serieeier (valgfritt)</label>
        <textarea id="message" name="message" rows="3" placeholder="F.eks. litt om klubben eller planer for sesongen"><?= $h((string) ($form['message'] ?? '')) ?></textarea>

        <p style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:.5rem;">
            <button type="submit" class="btn" name="submit_now" value="0">Lagre utkast</button>
            <button type="submit" class="btn" name="submit_now" value="1">Send inn søknad</button>
        </p>
    </form>
</div>
<?php endif; ?>
