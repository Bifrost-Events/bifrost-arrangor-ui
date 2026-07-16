<?php

declare(strict_types=1);

/** @var array<string, mixed> $space */
/** @var int $event_id */
/** @var int $owner_org_id */
/** @var string $event_name */
/** @var \App\Service\PortalEventTerminology $labels */
/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$spaceId = (int) ($space['space_id'] ?? 0);
?>
<div class="card" style="max-width:32rem;">
    <h1>Stevne opprettet</h1>
    <p><strong><?= $h($event_name) ?></strong> er lagret. Arrangøren er nå synlig i cupen via dette stevnet.</p>
    <p style="margin-top:1rem;">
        <a class="btn" href="<?= $h($pp::stevne((int) $event_id)) ?>">Åpne stevnet</a>
        <a class="btn secondary" href="<?= $h($pp::arrangor((int) $owner_org_id)) ?>">
            Gå til arrangør
        </a>
    </p>
    <p style="margin-top:.75rem;">
        <a href="<?= $h($pp::arrangorer()) ?>">← Tilbake til arrangører</a>
    </p>
</div>
