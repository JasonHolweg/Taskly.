<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();
$pdo = db();

$cosmeticId = (int) (body()['cosmetic_id'] ?? 0);
if ($cosmeticId <= 0) {
    fail('cosmetic_id fehlt.');
}

$st = $pdo->prepare('SELECT cost_sparks, name FROM cosmetics WHERE id = ?');
$st->execute([$cosmeticId]);
$c = $st->fetch();
if (!$c || $c['cost_sparks'] === null) {
    fail('Dieses Item ist nicht direkt kaufbar.', 409);
}

// Schon im Besitz?
$own = $pdo->prepare('SELECT 1 FROM user_cosmetics WHERE user_id = ? AND cosmetic_id = ?');
$own->execute([$uid, $cosmeticId]);
if ($own->fetchColumn()) {
    fail('Schon freigeschaltet.', 409);
}

$cost = (int) $c['cost_sparks'];
$p = get_progress($uid);
if ((int) $p['sparks'] < $cost) {
    fail('Zu wenig Sparks.', 409);
}

$pdo->prepare('UPDATE user_progress SET sparks = sparks - ? WHERE user_id = ?')->execute([$cost, $uid]);
$pdo->prepare('INSERT INTO user_cosmetics (user_id, cosmetic_id) VALUES (?, ?)')->execute([$uid, $cosmeticId]);

json_out(['ok' => true, 'sparks' => (int) get_progress($uid)['sparks']]);
