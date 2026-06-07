<?php
/**
 * Taskly — Tages-Cron: Streak-Abgleich für alle User (Eis/Bruch),
 * auch wenn jemand die App nicht öffnet. (rules.md §4)
 * Aufruf täglich früh:  php bin/cron_daily.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur via CLI.\n");
}

require __DIR__ . '/../src/bootstrap.php';

$users = db()->query('SELECT id FROM users')->fetchAll();
$n = 0;
foreach ($users as $u) {
    try {
        reconcile_streak((int) $u['id']);
        $n++;
    } catch (Throwable $e) {
        fwrite(STDERR, sprintf("[%s] User %d: %s\n", date('c'), (int) $u['id'], $e->getMessage()));
    }
}
printf("[%s] Streak-Abgleich: %d User\n", date('c'), $n);
