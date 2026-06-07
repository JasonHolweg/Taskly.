<?php
require __DIR__ . '/../../src/bootstrap.php';

$uid = current_user_id();
if ($uid === null) {
    json_out(['logged_in' => false]);
}

$st = db()->prepare('SELECT id, name, household_id FROM users WHERE id = ?');
$st->execute([$uid]);
$u = $st->fetch();
if (!$u) {
    json_out(['logged_in' => false]);
}

$p   = get_progress($uid);
$lvl = level_from_xp((int) $p['xp_total']);

json_out([
    'logged_in' => true,
    'user'      => ['id' => (int) $u['id'], 'name' => $u['name'], 'household_id' => (int) $u['household_id']],
    'progress'  => [
        'xp_total'       => (int) $p['xp_total'],
        'level'          => $lvl['level'],
        'xp_into'        => $lvl['xp_into'],
        'xp_needed'      => $lvl['xp_needed'],
        'sparks'         => (int) $p['sparks'],
        'streak'         => (int) $p['streak_count'],
        'longest_streak' => (int) $p['longest_streak'],
    ],
    'equipped'  => get_equipped($uid),
]);
