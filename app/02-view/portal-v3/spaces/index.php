<?php



declare(strict_types=1);



/** @var list<array<string, mixed>> $spaces */

/** @var string|null $api_error */

/** @var array{application_id: int, application_key: string, application_name: string, hostname: string}|null $domain_application */

/** @var \App\Service\PortalEventTerminology $labels */

/** @var string $route_prefix */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$appName = is_array($domain_application ?? null)

    ? (string) ($domain_application['application_name'] ?? '')

    : '';

?>

<h1><?= $appName !== '' ? $h($appName) : $h($labels->plural('event_space')) ?></h1>

<?php if ($appName !== ''): ?>

    <p class="muted">Cuper og arrangementer du administrerer for dette domenet.</p>

<?php else: ?>

    <p class="muted">Cuper og arrangementer du har administratortilgang til.</p>

<?php endif; ?>



<?php if (!empty($api_error)): ?>

    <div class="flash flash-error">

        <p><?= $h((string) $api_error) ?></p>

    </div>

<?php elseif ($spaces === []): ?>

    <div class="card">

        <p><?= $appName !== ''

            ? 'Ingen cuper/arrangementer for dette domenet som du kan administrere.'

            : 'Ingen cuper/arrangementer å vise.' ?></p>

    </div>

<?php else: ?>

    <div class="card">

        <table>

            <thead>

                <tr><th>Navn</th><th>Organisasjon</th><th></th></tr>

            </thead>

            <tbody>

            <?php foreach ($spaces as $space): ?>

                <tr>

                    <td><?= $h((string) ($space['name'] ?? '')) ?></td>

                    <td><?= $h((string) ($space['owner_org_name'] ?? $space['application_name'] ?? '')) ?></td>

                    <td><a class="btn" href="<?= $h($pp::cup()) ?>?space_id=<?= (int) ($space['space_id'] ?? 0) ?>">Åpne</a></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>

