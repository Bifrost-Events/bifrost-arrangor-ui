<?php

declare(strict_types=1);

/** @var string $route_prefix */
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="card" style="max-width:24rem;">
    <h1>Logg inn</h1>
    <p class="muted">Arrangørportal — Bifrost core og Events API.</p>
    <form method="post" action="<?= $h($pp::login()) ?>">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" required autocomplete="username">
        <label for="password">Passord</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <p style="margin-top:1rem;"><button type="submit" class="btn">Logg inn</button></p>
    </form>
    <p class="muted" style="margin-top:1.25rem; text-align:center;">
        Ny arrangør uten konto?<br>
        <a href="<?= $h($pp::komIGang()) ?>"><strong>Kom i gang</strong></a>
    </p>
</div>
