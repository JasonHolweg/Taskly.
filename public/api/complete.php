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

// Tanuki's Adventures: equipped Items multiplizieren echte Arbeit (XP/Sparks) —
// nie aus dem Nichts (journey.md §3). Unter L3 / Flag aus liefert der Helper 0.
$jb = ['xp' => 0.0, 'sparks' => 0.0];
try { $jb = journey_real_bonuses($uid); } catch (Throwable $e) { /* Kern nie brechen */
}
$baseXp = (int) $row['task_base_xp'];
if ($jb['xp'] > 0 && $baseXp > 0 && $row['task_type'] !== 'termin') {
    $baseXp = (int) round($baseXp * (1 + $jb['xp']));
}

$rewards = complete_occurrence(
    $uid,
    ['id' => (int) $row['id']],
    ['type' => $row['task_type'], 'base_xp' => $baseXp]
);

// Spark-Bonus-Items: Aufschlag auf die echten Erledigungs-Sparks.
try {
    if ($jb['sparks'] > 0 && !empty($rewards['sparks'])) {
        $extra = (int) round((int) $rewards['sparks'] * $jb['sparks']);
        if ($extra > 0) {
            db()->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')->execute([$extra, $uid]);
            $rewards['sparks'] = (int) $rewards['sparks'] + $extra;
        }
    }
} catch (Throwable $e) { /* bewusst geschluckt */
}

// Tanuki's Adventures (v2): Ausdauer + Distanz aus echter Erledigung — darf den Kern nie brechen.
try {
    $j = journey_on_complete($uid, (int) ($rewards['xp'] ?? 0));
    if ($j) {
        $rewards['journey'] = $j;
    }
} catch (Throwable $e) { /* bewusst geschluckt */
}

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
