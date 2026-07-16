<?php



declare(strict_types=1);



/** @var array<string, mixed> $space */

/** @var array<string, mixed> $series */

/** @var list<array{event: array<string, mixed>, can_edit: bool}> $events */

/** @var \App\Service\PortalEventTerminology $labels */

/** @var bool $can_create */

/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$spaceId = (int) ($space['space_id'] ?? 0);

$seriesId = (int) ($series['series_id'] ?? 0);

?>

<p><a href="<?= $h($pp::cup()) ?>">← <?= $h((string) ($space['name'] ?? '')) ?></a></p>

<h1><?= $h($labels->plural('event')) ?>: <?= $h((string) ($series['name'] ?? '')) ?></h1>



<?php if ($can_create): ?>

    <p><a class="btn" href="<?= $h($pp::sesongStevneNew($seriesId)) ?>">

        Nytt <?= $h(strtolower($labels->singular('event'))) ?>

    </a></p>

<?php endif; ?>



<div class="card">

    <?php if ($events === []): ?>

        <p>Ingen <?= $h(strtolower($labels->plural('event'))) ?>.</p>

    <?php else: ?>

        <table>

            <thead><tr><th>Navn</th><th>Eier</th><th>Sted</th><th>Start</th><th></th></tr></thead>

            <tbody>

            <?php foreach ($events as $row): ?>

                <?php $event = $row['event']; ?>

                <tr>

                    <td><?= $h((string) ($event['name'] ?? '')) ?></td>

                    <td class="muted"><?= $h((string) ($event['owner_org_name'] ?? '')) ?></td>

                    <td><?= $h((string) ($event['location_name'] ?? '')) ?></td>

                    <td><?= $h((string) ($event['starts_at'] ?? '')) ?></td>

                    <td>

                        <?php if ($row['can_edit']): ?>

                            <a href="<?= $h($pp::stevne((int) ($event['event_id'] ?? 0))) ?>">Rediger</a>

                        <?php else: ?>

                            <span class="muted">Kun visning</span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

