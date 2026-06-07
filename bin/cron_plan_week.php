<?php
/**
 * Taskly — Cron: wöchentliche KI-Verteilung für alle User.
 * Aufruf z.B. Montag früh:  php bin/cron_plan_week.php
 * (architecture.md §1 „Jobs", rules.md §6)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur via CLI.\n");
}

require __DIR__ . '/../src/bootstrap.php';

$started = microtime(true);
$users   = db()->query('SELECT id FROM users')->fetchAll();

$ok = 0; $fail = 0;
foreach ($users as $u) {
    $uid = (int) $u['id'];
    try {
        plan_week($uid);
        $ok++;
    } catch (Throwable $e) {
        $fail++;
        fwrite(STDERR, sprintf("[%s] User %d FEHLER: %s\n", date('c'), $uid, $e->getMessage()));
    }
}

printf(
    "[%s] Wochen-Verteilung: %d geplant, %d Fehler, %.1fs (KI: %s)\n",
    date('c'),
    $ok,
    $fail,
    microtime(true) - $started,
    claude_available() ? 'an' : 'Fallback'
);
