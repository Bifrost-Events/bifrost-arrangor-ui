<?php

declare(strict_types=1);

/** @var string $agreement_version */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

?>
<h2 style="margin-top:0;">Bli arrangør</h2>
<p class="lead">For å bruke arrangørportalen må du godta arrangøravtalen.</p>

<div class="placeholder-box">
    <p><strong>Arrangøravtale v<?= $h((string) ($agreement_version ?? '1.0')) ?></strong></p>
    <p class="muted">
        Som arrangør forplikter du deg til å følge cupens regler, behandle deltakerdata ansvarlig,
        og sørge for at stevner gjennomføres i tråd med gjeldende retningslinjer.
    </p>
</div>

<form method="post" action="/bli-arrangor/vilkar" class="form-grid">
    <label class="checkbox-row" style="display:flex;align-items:flex-start;gap:0.5rem;">
        <input type="checkbox" name="accept_terms" value="1" required style="width:auto;margin-top:0.2rem;">
        <span>Jeg godtar arrangøravtalen</span>
    </label>
    <button type="submit" class="btn btn-primary" style="width:auto;">Fortsett</button>
</form>
