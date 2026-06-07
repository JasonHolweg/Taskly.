<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

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
