<?php

declare(strict_types=1);

/** @var int $competition_id */
/** @var array<string, mixed> $competition */
/** @var string $active_view */
/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $stevne_admin */
/** @var array{ok: bool, data: array<string, mixed>|null, error: string|null} $roster */
/** @var array<string, mixed>|null $view_data */
/** @var array<string, mixed>|null $pameldelse_data */
/** @var list<array<string, mixed>> $participants */
/** @var array<string, mixed> $context */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$activeView = (string) ($active_view ?? 'pameldelse');
if (!in_array($activeView, ['pameldelse', 'gjennomfor'], true)) {
    $activeView = 'pameldelse';
}

include __DIR__ . '/_competition-flow.php';

if ($activeView === 'gjennomfor') {
    include __DIR__ . '/stevneadmin-gjennomfor.php';
} else {
    include __DIR__ . '/stevneadmin-pameldelse.php';
}

?>
