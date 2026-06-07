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

/** Ein Kosmetik-Item eines Themes + Rarität ziehen; degradiert auf die nächste
 *  vorhandene Rarität, falls das Theme die gewürfelte nicht hat. */
function draw_cosmetic(string $theme, string $rarity): ?array
{
    $pdo = db();

    // Welche Raritäten hat dieses Theme überhaupt?
    $av = $pdo->prepare("SELECT DISTINCT rarity FROM cosmetics WHERE theme = ? AND category='tanuki_outfit'");
    $av->execute([$theme]);
    $available = array_column($av->fetchAll(), 'rarity');
    if (!$available) {
        return null;
    }

    // Nächstgelegene vorhandene Rarität zur gewürfelten finden.
    if (!in_array($rarity, $available, true)) {
        $target = array_search($rarity, RARITY_ORDER, true);
        $best = null; $bestDist = 99;
        foreach ($available as $a) {
            $dist = abs(array_search($a, RARITY_ORDER, true) - $target);
            // bei Gleichstand die niedrigere Rarität bevorzugen
            if ($dist < $bestDist || ($dist === $bestDist && array_search($a, RARITY_ORDER, true) < array_search($best, RARITY_ORDER, true))) {
                $best = $a; $bestDist = $dist;
            }
        }
        $rarity = $best;
    }

    // Zufälliges Item dieser Theme+Rarität.
    $st = $pdo->prepare(
        "SELECT * FROM cosmetics
          WHERE theme = ? AND rarity = ? AND category='tanuki_outfit'
          ORDER BY RAND() LIMIT 1"
    );
    $st->execute([$theme, $rarity]);
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
    if ((int) $p['sparks'] < $cost) {
        return ['error' => 'Zu wenig Sparks.', 'need' => $cost, 'have' => (int) $p['sparks']];
    }

    // Sparks abbuchen.
    $pdo->prepare('UPDATE user_progress SET sparks = sparks - ? WHERE user_id = ?')
        ->execute([$cost, $userId]);

    // Soft-Pity: nach (soft_pity-1) Boxen ohne Episch+ ist Episch+ garantiert.
    $forced = (int) $p['pity_counter'] >= ((int) $t['soft_pity'] - 1);
    $rolled = roll_rarity($forced);

    $cosmetic = draw_cosmetic($box['theme'], $rolled);
    if (!$cosmetic) {
        // Sicherheitsnetz: Sparks zurück, falls Theme leer.
        $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')
            ->execute([$cost, $userId]);
        return ['error' => 'Diese Kiste ist gerade leer.'];
    }
    $rarity = $cosmetic['rarity'];

    // Besitzt der User das Item schon?
    $own = $pdo->prepare('SELECT 1 FROM user_cosmetics WHERE user_id = ? AND cosmetic_id = ?');
    $own->execute([$userId, $cosmetic['id']]);
    $isDupe = (bool) $own->fetchColumn();

    $refund = 0;
    if ($isDupe) {
        $refund = (int) ($t['dupe_refund'][$rarity] ?? 0);
        $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')
            ->execute([$refund, $userId]);
    } else {
        $pdo->prepare('INSERT INTO user_cosmetics (user_id, cosmetic_id) VALUES (?, ?)')
            ->execute([$userId, $cosmetic['id']]);
    }

    // Pity fortschreiben: Episch+ setzt zurück, sonst +1.
    $newPity = in_array($rarity, ['episch', 'legendaer'], true) ? 0 : ((int) $p['pity_counter'] + 1);
    $pdo->prepare('UPDATE user_progress SET pity_counter = ? WHERE user_id = ?')
        ->execute([$newPity, $userId]);

    // Audit-Log.
    $pdo->prepare(
        'INSERT INTO lootbox_openings (user_id, lootbox_id, cosmetic_id, was_duplicate, sparks_refund)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $box['id'], $cosmetic['id'], $isDupe ? 1 : 0, $refund]);

    $sparks = (int) get_progress($userId)['sparks'];

    return [
        'ok'        => true,
        'cosmetic'  => cosmetic_dto($cosmetic),
        'rarity'    => $rarity,
        'duplicate' => $isDupe,
        'refund'    => $refund,
        'sparks'    => $sparks,
        'pity'      => $newPity,
        'pity_max'  => (int) $t['soft_pity'],
    ];
}
