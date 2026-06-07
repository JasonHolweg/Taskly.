<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

$occId = (int) (body()['occ_id'] ?? 0);
if ($occId <= 0) {
    fail('occ_id fehlt.');
}

// Occurrence + Task laden, Eigentum prüfen (gleicher Haushalt).
$st = db()->prepare(
    'SELECT o.*, t.type AS task_type, t.base_xp AS task_base_xp
       FROM task_occurrences o
       JOIN tasks t ON t.id = o.task_id
       JOIN users u ON u.household_id = t.household_id
      WHERE o.id = ? AND u.id = ?'
);
$st->execute([$occId, $uid]);
$row = $st->fetch();
if (!$row) {
    fail('Aufgabe nicht gefunden.', 404);
}
if ($row['status'] === 'done') {
    fail('Schon erledigt.', 409);
}

$rewards = complete_occurrence(
    $uid,
    ['id' => (int) $row['id']],
    ['type' => $row['task_type'], 'base_xp' => (int) $row['task_base_xp']]
);

// Frischer Fortschritt fürs Header-UI
$p   = get_progress($uid);
$lvl = level_from_xp((int) $p['xp_total']);

json_out([
    'ok'       => true,
    'rewards'  => $rewards,
    'progress' => [
        'xp_total'  => (int) $p['xp_total'],
        'level'     => $lvl['level'],
        'xp_into'   => $lvl['xp_into'],
        'xp_needed' => $lvl['xp_needed'],
        'sparks'    => (int) $p['sparks'],
        'streak'    => (int) $p['streak_count'],
    ],
]);
