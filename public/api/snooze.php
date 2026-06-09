<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/_pick.php';
require_method('POST');
$uid = require_auth();

$b       = body();
$occId   = (int) ($b['occ_id'] ?? 0);
$minutes = (int) ($b['minutes'] ?? 180); // Default: 3 Stunden
if ($occId <= 0) {
    fail('occ_id fehlt.');
}
$minutes = max(5, min(60 * 24 * 14, $minutes));

// Snooze verschiebt, löscht nie (rules.md §8). Nur eigene Haushalts-Occurrence.
$st = db()->prepare(
    "UPDATE task_occurrences o
       JOIN tasks t ON t.id = o.task_id
       JOIN users u ON u.household_id = t.household_id
        SET o.status = 'snoozed',
            o.snoozed_until = DATE_ADD(NOW(), INTERVAL :min MINUTE)
      WHERE o.id = :occ AND u.id = :uid AND o.status != 'done'"
);
$st->execute([':min' => $minutes, ':occ' => $occId, ':uid' => $uid]);

// Aus der Session-Skip-Liste raus (ist ja jetzt geparkt, nicht geskippt).
$_SESSION['skips'] = array_values(array_diff(
    array_map('intval', $_SESSION['skips'] ?? []),
    [$occId]
));

json_out(pick_response($uid, false));
