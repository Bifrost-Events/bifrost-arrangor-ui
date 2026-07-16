<?php



declare(strict_types=1);



/** @var array<string, mixed> $space */

/** @var list<array<string, mixed>> $roots */

/** @var array<int, list<array<string, mixed>>> $children */

/** @var \App\Service\PortalEventTerminology $labels */

/** @var bool $can_edit_space */

/** @var bool $can_manage_series */

/** @var bool $can_create_series */

/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$spaceId = (int) ($space['space_id'] ?? 0);

?>

<h1><?= $h($labels->plural('series')) ?></h1>
<p class="muted"><?= $h($labels->plural('series')) ?> og <?= $h($labels->plural('subseries')) ?> i denne <?= $h(strtolower($labels->singular('event_space'))) ?></p>



<?php if ($can_edit_space): ?>

    <p>

        <a class="btn secondary" href="<?= $h($pp::cupEdit()) ?>">Rediger <?= $h(strtolower($labels->singular('event_space'))) ?></a>

        <a class="btn secondary" href="<?= $h($pp::stevner()) ?>">Alle <?= $h(strtolower($labels->plural('event'))) ?></a>

    </p>

<?php endif; ?>



<?php if ($can_create_series): ?>

    <p><a class="btn" href="<?= $h($pp::sesongNew()) ?>">

        Ny <?= $h(strtolower($labels->singular('series'))) ?>

    </a></p>

<?php endif; ?>



<?php foreach ($roots as $root): ?>

    <?php $rootId = (int) ($root['series_id'] ?? 0); ?>

    <div class="card">

        <h2>

            <?= $h((string) ($root['name'] ?? '')) ?>

            <?php if ($can_manage_series): ?>

                <a class="btn secondary" style="font-size:.85rem;margin-left:.5rem;" href="<?= $h($pp::sesongEdit($rootId)) ?>">Rediger</a>

            <?php endif; ?>

        </h2>

        <p class="muted"><?= $h($labels->singular('series')) ?> · <?= $h((string) ($root['series_type'] ?? '')) ?></p>

        <?php $subs = $children[$rootId] ?? []; ?>

        <?php if ($subs === []): ?>

            <p>

                <a class="btn" href="<?= $h($pp::sesongStevner($rootId)) ?>">

                    <?= $h($labels->plural('event')) ?>

                </a>

                <?php if ($can_manage_series): ?>

                    <a class="btn secondary" href="<?= $h($pp::sesongChildNew($rootId)) ?>">

                        Ny <?= $h(strtolower($labels->singular('subseries'))) ?>

                    </a>

                <?php endif; ?>

            </p>

        <?php else: ?>

            <ul>

            <?php foreach ($subs as $sub): ?>

                <?php $subId = (int) ($sub['series_id'] ?? 0); ?>

                <li>

                    <strong><?= $h((string) ($sub['name'] ?? '')) ?></strong>

                    <span class="muted">(<?= $h($labels->singular('subseries')) ?>)</span>

                    <a class="btn" href="<?= $h($pp::sesongStevner($subId)) ?>">

                        <?= $h($labels->plural('event')) ?>

                    </a>

                    <?php if ($can_manage_series): ?>

                        <a href="<?= $h($pp::sesongEdit($subId)) ?>">Rediger</a>

                    <?php endif; ?>

                </li>

            <?php endforeach; ?>

            </ul>

            <?php if ($can_manage_series): ?>

                <p><a class="btn secondary" href="<?= $h($pp::sesongChildNew($rootId)) ?>">

                    Ny <?= $h(strtolower($labels->singular('subseries'))) ?>

                </a></p>

            <?php endif; ?>

        <?php endif; ?>

    </div>

<?php endforeach; ?>



<?php if ($roots === []): ?>

    <div class="card"><p>Ingen <?= $h(strtolower($labels->plural('series'))) ?> i dette space.</p></div>

<?php endif; ?>

