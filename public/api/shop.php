<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();

$pdo = db();
$p   = get_progress($uid);

// Besitz-Set des Users
$ownSt = $pdo->prepare('SELECT cosmetic_id FROM user_cosmetics WHERE user_id = ?');
$ownSt->execute([$uid]);
$owned = array_map('intval', array_column($ownSt->fetchAll(), 'cosmetic_id'));

// Aktive Lootboxen + Inhalt (Vorschau, mit owned-Flag)
$boxes = [];
foreach ($pdo->query("SELECT * FROM lootboxes WHERE active = 1")->fetchAll() as $box) {
    $cs = $pdo->prepare(
        "SELECT * FROM cosmetics
          WHERE theme = ? AND category='tanuki_outfit'
          ORDER BY FIELD(rarity,'legendaer','episch','selten','gewoehnlich'), name"
    );
    $cs->execute([$box['theme']]);

    $contents = [];
    $counts = ['gewoehnlich' => 0, 'selten' => 0, 'episch' => 0, 'legendaer' => 0];
    foreach ($cs->fetchAll() as $c) {
        $dto = cosmetic_dto($c);
        $dto['owned'] = in_array((int) $c['id'], $owned, true);
        $contents[] = $dto;
        $counts[$c['rarity']] = ($counts[$c['rarity']] ?? 0) + 1;
    }

    $boxes[] = [
        'id'         => (int) $box['id'],
        'name'       => $box['name'],
        'theme'      => $box['theme'],
        'cost'       => (int) $box['cost_sparks'],
        'counts'     => $counts,
        'contents'   => $contents,
        'img_closed' => '/assets/img/pochibukuro/' . $box['theme'] . '-closed.png',
        'img_open'   => '/assets/img/pochibukuro/' . $box['theme'] . '-open.png',
    ];
}

global $CONFIG;
json_out([
    'sparks'      => (int) $p['sparks'],
    'pity'        => (int) $p['pity_counter'],
    'pity_max'    => (int) $CONFIG['tuning']['soft_pity'],
    'drop_rates'  => $CONFIG['tuning']['drop_rates'],
    'boxes'       => $boxes,
    'inventory'   => get_inventory($uid),
    'equipped_id' => (int) ($pdo->query("SELECT equipped_outfit_id FROM tanuki_profile WHERE user_id={$uid}")->fetchColumn() ?: 0),
]);
