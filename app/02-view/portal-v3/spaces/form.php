<?php



declare(strict_types=1);



/** @var array<string, mixed> $space */

/** @var \App\Service\PortalEventTerminology $labels */

/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$spaceId = (int) ($space['space_id'] ?? 0);

?>

<p><a href="<?= $h($pp::cup()) ?>">← <?= $h((string) ($space['name'] ?? '')) ?></a></p>

<h1>Rediger <?= $h(strtolower($labels->singular('event_space'))) ?></h1>



<div class="card" style="max-width:36rem;">

    <form method="post" action="<?= $h($pp::cupEdit()) ?>">

        <label for="name">Navn *</label>

        <input type="text" id="name" name="name" required value="<?= $h((string) ($space['name'] ?? '')) ?>">



        <label for="short_name">Kortnavn</label>

        <input type="text" id="short_name" name="short_name" value="<?= $h((string) ($space['short_name'] ?? '')) ?>">



        <label for="slug">Slug</label>

        <input type="text" id="slug" name="slug" value="<?= $h((string) ($space['slug'] ?? '')) ?>">



        <label for="description">Beskrivelse</label>

        <textarea id="description" name="description" rows="3"><?= $h((string) ($space['description'] ?? '')) ?></textarea>



        <label for="status">Status</label>

        <select id="status" name="status">

            <?php foreach (['draft', 'active', 'inactive'] as $st): ?>

                <option value="<?= $st ?>" <?= (($space['status'] ?? 'active') === $st) ? 'selected' : '' ?>><?= $h($st) ?></option>

            <?php endforeach; ?>

        </select>



        <label for="visibility">Synlighet</label>

        <select id="visibility" name="visibility">

            <?php foreach (['internal', 'public', 'private'] as $vis): ?>

                <option value="<?= $vis ?>" <?= (($space['visibility'] ?? 'internal') === $vis) ? 'selected' : '' ?>><?= $h($vis) ?></option>

            <?php endforeach; ?>

        </select>



        <label for="ui_labels_json">UI-labels (JSON)</label>

        <textarea id="ui_labels_json" name="ui_labels_json" rows="6"><?= $h((string) ($space['ui_labels_json'] ?? '')) ?></textarea>

        <p class="muted">Overstyr domenetilpassede begreper for dette Event Space.</p>



        <p style="margin-top:1rem;"><button type="submit" class="btn">Lagre</button></p>

    </form>

</div>

