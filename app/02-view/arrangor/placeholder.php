<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $description */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

?>
<h2 style="margin-top:0;"><?= $h($title) ?></h2>
<p class="lead"><?= $h($description) ?></p>
<div class="placeholder-box">
    <p><strong>Kommer senere</strong></p>
    <p class="muted">Denne siden er en placeholder. CRUD og funksjonalitet bygges i senere iterasjoner.</p>
</div>
