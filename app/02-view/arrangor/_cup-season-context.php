<?php

declare(strict_types=1);

/** @var array<string, mixed> $organizer_context */
/** @var callable $h */

$ctx = is_array($organizer_context ?? null) ? $organizer_context : [];

$tenant = is_array($ctx['tenant'] ?? null) ? $ctx['tenant'] : null;
$cupName = trim((string) ($ctx['tenant_name'] ?? ''));
if ($cupName === '' && $tenant !== null) {
    $cupName = trim((string) ($tenant['name'] ?? ''));
}

$season = is_array($ctx['selected_season'] ?? null) ? $ctx['selected_season'] : null;
$seasonLabel = '';
if ($season !== null) {
    $name = trim((string) ($season['name'] ?? ''));
    $year = (int) ($season['year'] ?? 0);
    if ($name !== '' && ($year <= 0 || str_contains($name, (string) $year))) {
        $seasonLabel = $name;
    } elseif ($name !== '' && $year > 0) {
        $seasonLabel = $name . ' ' . $year;
    } elseif ($year > 0) {
        $seasonLabel = (string) $year;
    } else {
        $seasonLabel = $name;
    }
}

if ($cupName === '' && $seasonLabel === '') {
    return;
}
?>
<div class="cup-season-context" aria-label="Cup og sesong">
    <?php if ($cupName !== ''): ?>
        <span class="cup-season-context__item">
            <span class="cup-season-context__label">Cup</span>
            <strong><?= $h($cupName) ?></strong>
        </span>
    <?php endif; ?>
    <?php if ($seasonLabel !== ''): ?>
        <span class="cup-season-context__item">
            <span class="cup-season-context__label">Sesong</span>
            <strong><?= $h($seasonLabel) ?></strong>
        </span>
    <?php endif; ?>
</div>
