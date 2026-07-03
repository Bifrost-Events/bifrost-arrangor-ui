<?php

declare(strict_types=1);

/** @var string $title */
/** @var string $token */
/** @var string $error */

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = $error ?? '';
$token = $token ?? '';

?>
<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($title) ?> – Bifrost Arrangør</title>
    <style>
        :root { --bg: #f4f4f2; --ink: #1a1a18; --muted: #5c5c58; --line: #d8d8d4; --accent: #2c5530; --bad: #9b2c2c; }
        body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.5rem; max-width: 420px; width: 100%; }
        .error { background: #fdeaea; color: var(--bad); padding: 0.65rem; border-radius: 4px; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.65rem; background: var(--accent); color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        .muted { color: var(--muted); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="margin-top:0;font-size:1.25rem;">Aksepter invitasjon</h1>
        <p class="muted">Bekreft at du vil bli med i arrangørorganisasjonen.</p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>

        <?php if ($token !== ''): ?>
            <form method="post" action="/invitasjon/aksepter">
                <input type="hidden" name="token" value="<?= $h($token) ?>">
                <button type="submit">Aksepter invitasjon</button>
            </form>
        <?php else: ?>
            <p class="error">Manglende invitasjonstoken i lenken.</p>
        <?php endif; ?>
    </div>
</body>
</html>
