<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

// v2 (journey.md §8): Shop schaltet erst ab Level 4 frei — nur mit aktivem Feature-Flag.
if (journey_enabled() && journey_user_level($uid) < journey_cfg()['shop_unlock_level']) {
    fail('Der Shop schaltet ab Level 4 frei. 🦝', 403);
}

$boxId = (int) (body()['box_id'] ?? 0);
if ($boxId <= 0) {
    fail('box_id fehlt.');
}

$st = db()->prepare('SELECT * FROM lootboxes WHERE id = ? AND active = 1');
$st->execute([$boxId]);
$box = $st->fetch();
if (!$box) {
    fail('Kiste nicht gefunden.', 404);
}

$result = open_lootbox($uid, $box);
if (isset($result['error'])) {
    fail($result['error'], 409);
}

json_out($result);
