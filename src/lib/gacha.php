<?php
/**
 * Taskly — Lootbox-Gacha (rules.md §3).
 * Rarität würfeln (mit Soft-Pity) → Item ziehen → Dupe-Rückerstattung.
 */
declare(strict_types=1);

const RARITY_ORDER = ['gewoehnlich', 'selten', 'episch', 'legendaer'];

/** Rarität gewichtet würfeln. $forced = nur Episch+ (Soft-Pity-Garantie). */
function roll_rarity(bool $forced = false): string
{
    global $CONFIG;
    $rates = $CONFIG['tuning']['drop_rates']; // gewoehnlich/selten/episch/legendaer
    if ($forced) {
        $rates = ['episch' => $rates['episch'], 'legendaer' => $rates['legendaer']];
    }
    $total = array_sum($rates);
    $r = random_int(1, (int) $total);
    $acc = 0;
    foreach ($rates as $rarity => $w) {
        $acc += $w;
        if ($r <= $acc) {
            return $rarity;
        }
    }
    return array_key_last($rates);
}

/** Ein NOCH NICHT besessenes Item eines Themes ziehen. Würfelt die Rarität,
 *  degradiert aber auf die nächste Rarität, die der User in diesem Theme noch
 *  nicht komplett hat. Gibt null zurück, wenn das Theme bereits 100% ist. */
function draw_cosmetic(string $theme, string $rarity, int $userId): ?array
{
    $pdo = db();

    // Welche Raritäten haben in diesem Theme noch ungesammelte Items?
    $av = $pdo->prepare(
        "SELECT DISTINCT c.rarity FROM cosmetics c
          WHERE c.theme = ? AND c.category='tanuki_outfit'
            AND c.id NOT IN (SELECT cosmetic_id FROM user_cosmetics WHERE user_id = ?)"
    );
    $av->execute([$theme, $userId]);
    $available = array_column($av->fetchAll(), 'rarity');
    if (!$available) {
        return null; // alles freigeschaltet
    }

    // Nächstgelegene noch-offene Rarität zur gewürfelten finden.
    if (!in_array($rarity, $available, true)) {
        $target = array_search($rarity, RARITY_ORDER, true);
        $best = null; $bestDist = 99;
        foreach ($available as $a) {
            $dist = abs(array_search($a, RARITY_ORDER, true) - $target);
            if ($dist < $bestDist || ($dist === $bestDist && array_search($a, RARITY_ORDER, true) < array_search($best, RARITY_ORDER, true))) {
                $best = $a; $bestDist = $dist;
            }
        }
        $rarity = $best;
    }

    // Zufälliges, noch nicht besessenes Item dieser Theme+Rarität.
    $st = $pdo->prepare(
        "SELECT * FROM cosmetics
          WHERE theme = ? AND rarity = ? AND category='tanuki_outfit'
            AND id NOT IN (SELECT cosmetic_id FROM user_cosmetics WHERE user_id = ?)
          ORDER BY RAND() LIMIT 1"
    );
    $st->execute([$theme, $rarity, $userId]);
    return $st->fetch() ?: null;
}

/**
 * Eine Lootbox öffnen. Gibt das Ergebnis-Paket zurück oder ['error'=>…].
 */
function open_lootbox(int $userId, array $box): array
{
    global $CONFIG;
    $pdo = db();
    $t = $CONFIG['tuning'];
    $cost = (int) $box['cost_sparks'];

    $p = get_progress($userId);

    // Schon zu 100% freigeschaltet? Dann nicht öffnen (Kauf ist im UI gesperrt).
    [$owned, $total] = theme_progress($userId, $box['theme']);
    if ($total > 0 && $owned >= $total) {
        return ['error' => 'Diese Kiste ist bereits zu 100% freigeschaltet.'];
    }
    if ((int) $p['sparks'] < $cost) {
        return ['error' => 'Zu wenig Sparks.', 'need' => $cost, 'have' => (int) $p['sparks']];
    }

    // Rarität würfeln (Soft-Pity garantiert Episch+ nach soft_pity-1 erfolglosen Boxen)
    // und ein NOCH NICHT besessenes Item dieser/nächster offener Rarität ziehen.
    $forced   = (int) $p['pity_counter'] >= ((int) $t['soft_pity'] - 1);
    $rolled   = roll_rarity($forced);
    $cosmetic = draw_cosmetic($box['theme'], $rolled, $userId);
    if (!$cosmetic) {
        return ['error' => 'Diese Kiste ist bereits zu 100% freigeschaltet.'];
    }
    $rarity = $cosmetic['rarity'];

    // Sparks abbuchen + Item gutschreiben (immer neu, daher kein Dupe/Refund).
    $pdo->prepare('UPDATE user_progress SET sparks = sparks - ? WHERE user_id = ?')->execute([$cost, $userId]);
    $pdo->prepare('INSERT INTO user_cosmetics (user_id, cosmetic_id) VALUES (?, ?)')
        ->execute([$userId, $cosmetic['id']]);

    // Pity fortschreiben: Episch+ setzt zurück, sonst +1.
    $newPity = in_array($rarity, ['episch', 'legendaer'], true) ? 0 : ((int) $p['pity_counter'] + 1);
    $pdo->prepare('UPDATE user_progress SET pity_counter = ? WHERE user_id = ?')->execute([$newPity, $userId]);

    // Audit-Log.
    $pdo->prepare(
        'INSERT INTO lootbox_openings (user_id, lootbox_id, cosmetic_id, was_duplicate, sparks_refund)
         VALUES (?, ?, ?, 0, 0)'
    )->execute([$userId, $box['id'], $cosmetic['id']]);

    $sparks = (int) get_progress($userId)['sparks'];
    [$owned2, $total2] = theme_progress($userId, $box['theme']);

    return [
        'ok'        => true,
        'cosmetic'  => cosmetic_dto($cosmetic),
        'rarity'    => $rarity,
        'duplicate' => false,
        'refund'    => 0,
        'sparks'    => $sparks,
        'pity'      => $newPity,
        'pity_max'  => (int) $t['soft_pity'],
        'owned'     => $owned2,
        'total'     => $total2,
        'complete'  => ($owned2 >= $total2),
    ];
}
