<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

$b          = body();
$cosmeticId = (int) ($b['cosmetic_id'] ?? 0);
$kind       = (string) ($b['kind'] ?? 'outfit');
$pdo = db();

// --- Rahmen (Frame-Kosmetik) ---
if ($kind === 'frame') {
    if ($cosmeticId === 0) {
        $pdo->prepare('UPDATE tanuki_profile SET equipped_frame_id = NULL WHERE user_id = ?')->execute([$uid]);
        json_out(['ok' => true, 'frame' => 'default']);
    }
    $own = $pdo->prepare(
        "SELECT c.asset_ref FROM user_cosmetics uc JOIN cosmetics c ON c.id = uc.cosmetic_id
          WHERE uc.user_id = ? AND uc.cosmetic_id = ? AND c.category = 'frame'"
    );
    $own->execute([$uid, $cosmeticId]);
    $variant = $own->fetchColumn();
    if (!$variant) {
        fail('Rahmen nicht im Inventar.', 404);
    }
    $pdo->prepare('UPDATE tanuki_profile SET equipped_frame_id = ? WHERE user_id = ?')->execute([$cosmeticId, $uid]);
    json_out(['ok' => true, 'frame' => $variant]);
}

// 0 = Outfit ablegen → Basis-Tanuki
if ($cosmeticId === 0) {
    $pdo->prepare('UPDATE user_cosmetics SET equipped = 0 WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('UPDATE tanuki_profile SET equipped_outfit_id = NULL WHERE user_id = ?')->execute([$uid]);
    json_out(['ok' => true, 'equipped' => null]);
}

// Besitz prüfen
$own = $pdo->prepare(
    "SELECT 1 FROM user_cosmetics uc JOIN cosmetics c ON c.id = uc.cosmetic_id
      WHERE uc.user_id = ? AND uc.cosmetic_id = ? AND c.category = 'tanuki_outfit'"
);
$own->execute([$uid, $cosmeticId]);
if (!$own->fetchColumn()) {
    fail('Outfit nicht im Inventar.', 404);
}

// Andere ablegen, dieses anlegen
$pdo->prepare('UPDATE user_cosmetics SET equipped = 0 WHERE user_id = ?')->execute([$uid]);
$pdo->prepare('UPDATE user_cosmetics SET equipped = 1 WHERE user_id = ? AND cosmetic_id = ?')
    ->execute([$uid, $cosmeticId]);
$pdo->prepare('UPDATE tanuki_profile SET equipped_outfit_id = ? WHERE user_id = ?')
    ->execute([$cosmeticId, $uid]);

json_out(['ok' => true, 'equipped' => get_equipped($uid)]);
