<?php
/**
 * Taskly v2 — „Tanuki's Adventures" (journey.md).
 * Reise-Engine: Ausdauer, passive Bewegung, Wegpunkte, Events, Items.
 * Komplett deterministisch — keine LLM-Calls.
 *
 * EISERNE REGEL (journey.md §0, nicht verhandelbar):
 * Distanz steigt NUR durch (a) erledigte Aufgaben (XP → Meter) und
 * (b) passive Bewegung, die Ausdauer verbraucht — und Ausdauer gibt es
 * NUR für erledigte Aufgaben. 0 Aufgaben = 0 Fortschritt. Ausdauer nie < 0.
 */
declare(strict_types=1);

/** Reihenfolge der Item-Raritäten (wie gacha.php RARITY_ORDER, eigene Konstante
 *  um die v1-Lib nicht anzufassen). */
const JOURNEY_RARITY_ORDER = ['gewoehnlich', 'selten', 'episch', 'legendaer'];

/**
 * Stellschrauben (journey.md §10) — Defaults, überschreibbar via $CONFIG['journey'].
 * Der Server-Config darf der Key komplett fehlen.
 */
function journey_cfg(): array
{
    global $CONFIG;
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $defaults = [
        'enabled'                 => true,   // Feature-Flag: aus = v1-Verhalten, Endpoints 404
        'unlock_level'            => 3,      // §8: Reise ab Level 3 — neuer Nutzer sieht nie alles auf einmal
        'shop_unlock_level'       => 4,      // §8: Shop erst ab Level 4 — Sparks stauen sich an (Anticipation)
        'm_per_xp'                => 100,    // §2: 1 XP = 100 m → 40-XP-Task = +4 km, fühlbarer Sofort-Schub
        'passive_kmh'             => 1.0,    // §2: ~1 km/h passive Wandergeschwindigkeit — gemütlich, nie dominant
        'stamina_max_m'           => 12000,  // §2: max 12 km Idle-Strecke zwischen zwei Aufgaben, dann rastet er
        'stamina_per_task_m'      => 1000,   // §2: +1 km Treibstoff pro erledigter Aufgabe (= „+1 Ausdauer")
        'equip_slots'             => 3,      // §3: 3 Slots → man wählt sein Setup, kein Tabellen-Spiel
        'boost_pct'               => 15,     // §7: Reise-Boost +15 % — spürbar, aber kein Pflichtgefühl
        'boost_hours'             => 24,     // §7: Boost-Dauer zeitlich begrenzt (verdient, kein Echtgeld)
        'event_count'             => 3,      // §5: 3 Zufalls-Events pro Reise („3/5 entdeckt"-Gefühl)
        'event_min_gap_km'        => 5,      // Events nicht klumpen — Mindestabstand auf der Strecke
        'arrival_fallback_sparks' => 120,    // Theme komplett? Gratis-Zug wird zu Sparks (~1 Boxpreis)
        'item_fallback_sparks'    => 25,     // Alle Reise-Items besessen? Kleiner Sparks-Trost statt Leerlauf
    ];
    $cfg = array_merge($defaults, $CONFIG['journey'] ?? []);
    return $cfg;
}

/** Feature an? */
function journey_enabled(): bool
{
    return (bool) journey_cfg()['enabled'];
}

/** Aktuelles Level des Users (aus echter XP-Kurve, rules.md §2). */
function journey_user_level(int $uid): int
{
    $p = get_progress($uid);
    return (int) level_from_xp((int) $p['xp_total'])['level'];
}

/** Zugangs-Gate für Reise-Endpoints (journey.md §8). */
function journey_gate(int $uid): void
{
    $cfg = journey_cfg();
    if (!journey_enabled()) {
        fail('Nicht gefunden.', 404);
    }
    if (journey_user_level($uid) < (int) $cfg['unlock_level']) {
        fail("Tanuki's Adventures schaltet ab Level {$cfg['unlock_level']} frei. 🦝", 403);
    }
}

/** tanuki_profile-Zeile sicherstellen + Reise-Attribute zurückgeben. */
function ensure_tanuki(int $uid): array
{
    $pdo = db();
    $pdo->prepare('INSERT IGNORE INTO tanuki_profile (user_id) VALUES (?)')->execute([$uid]);
    // SELECT * + ??-Defaults: robust, auch wenn die Bonus-Spalten-Migration noch fehlt.
    $st = $pdo->prepare('SELECT * FROM tanuki_profile WHERE user_id = ?');
    $st->execute([$uid]);
    $t = $st->fetch() ?: [];
    return [
        'stamina'       => (int) ($t['stamina'] ?? 0),
        'stamina_max'   => (int) ($t['stamina_max'] ?? journey_cfg()['stamina_max_m']),
        'speed_bonus'   => (float) ($t['speed_bonus'] ?? 0),
        'loot_bonus'    => (float) ($t['loot_bonus'] ?? 0),
        'xp_bonus'      => (float) ($t['xp_bonus'] ?? 0),
        'sparks_bonus'  => (float) ($t['sparks_bonus'] ?? 0),
        'stamina_bonus' => (float) ($t['stamina_bonus'] ?? 0),
    ];
}

/**
 * Echte Wirtschafts-Boni aus equipped Items (XP & Sparks) — fürs complete.php.
 * Multipliziert ausschließlich reale Erledigungen (journey.md §3); unter Level 3
 * oder bei deaktiviertem Flag immer 0.
 */
function journey_real_bonuses(int $uid): array
{
    $cfg = journey_cfg();
    if (!journey_enabled() || journey_user_level($uid) < (int) $cfg['unlock_level']) {
        return ['xp' => 0.0, 'sparks' => 0.0];
    }
    $t = ensure_tanuki($uid);
    return ['xp' => $t['xp_bonus'], 'sparks' => $t['sparks_bonus']];
}

/** Aktive Reise des Users (oder null). */
function journey_active(int $uid): ?array
{
    $st = db()->prepare("SELECT * FROM journeys WHERE user_id = ? AND status = 'active' LIMIT 1");
    $st->execute([$uid]);
    return $st->fetch() ?: null;
}

/**
 * Passive Bewegung abrechnen (journey.md §2): seit last_tick_at läuft der
 * Tanuki ~1 km/h, solange Ausdauer da ist. Bewegung verbraucht Ausdauer 1:1
 * (Meter), Ausdauer kommt NUR aus Aufgaben → eiserne Regel bleibt intakt.
 */
function journey_tick(int $uid): array
{
    if (!journey_enabled()) {
        return ['moved_m' => 0, 'events' => []];
    }
    $cfg = journey_cfg();
    ensure_tanuki($uid);
    $pdo = db();

    $pdo->beginTransaction();
    try {
        // Reise + Profil sperren (Lock-Reihenfolge überall gleich: journeys → tanuki_profile).
        // Zeitrechnung komplett in DB-Zeit (Verbindung läuft auf UTC) — kein PHP/SQL-Drift.
        $st = $pdo->prepare(
            "SELECT j.*,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, COALESCE(j.last_tick_at, j.started_at), NOW())) AS elapsed_s,
                    CASE WHEN j.boost_until IS NULL OR j.boost_pct <= 0 THEN 0
                         ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, COALESCE(j.last_tick_at, j.started_at),
                                                        LEAST(NOW(), j.boost_until))) END AS boosted_s
               FROM journeys j
              WHERE j.user_id = ? AND j.status = 'active'
              LIMIT 1
                FOR UPDATE"
        );
        $st->execute([$uid]);
        $j = $st->fetch();
        if (!$j) {
            $pdo->commit();
            return ['moved_m' => 0, 'events' => []];
        }

        $tp = $pdo->prepare('SELECT stamina, speed_bonus FROM tanuki_profile WHERE user_id = ? FOR UPDATE');
        $tp->execute([$uid]);
        $t = $tp->fetch();
        $stamina    = max(0, (int) ($t['stamina'] ?? 0)); // Defensiv: nie mit negativem Wert rechnen
        $speedBonus = (float) ($t['speed_bonus'] ?? 0);

        $elapsedS = (int) $j['elapsed_s'];
        $boostedS = min((int) $j['boosted_s'], $elapsedS);
        $restS    = $elapsedS - $boostedS;
        $boostPct = (int) $j['boost_pct'];

        // Meter pro Stunde inkl. Item-Speed-Bonus; Boost wirkt nur auf den geboosteten Zeitanteil.
        $mPerH = $cfg['passive_kmh'] * 1000 * (1 + $speedBonus);
        $potentialM = (int) floor($boostedS / 3600 * $mPerH * (1 + $boostPct / 100))
                    + (int) floor($restS / 3600 * $mPerH);

        $capM  = (int) $j['total_km'] * 1000;
        $distM = (int) $j['distance_m'];
        // Bewegung ist hart durch Ausdauer gedeckelt (eiserne Regel) + Streckenende.
        $moveM = max(0, min($potentialM, $stamina, $capM - $distM));

        $newStamina = $stamina - $moveM;          // >= 0, da $moveM <= $stamina
        $newDistM   = $distM + $moveM;

        // KRITISCH: last_tick_at IMMER auf NOW() — auch bei moveM = 0. Sonst würde
        // später verdiente Ausdauer rückwirkend Wartezeit vergüten (Bruch von §0).
        $pdo->prepare('UPDATE journeys SET distance_m = ?, last_tick_at = NOW() WHERE id = ?')
            ->execute([$newDistM, (int) $j['id']]);
        $pdo->prepare('UPDATE tanuki_profile SET stamina = ? WHERE user_id = ?')
            ->execute([$newStamina, $uid]);

        $j['distance_m'] = $newDistM;
        $events = journey_process_crossings($pdo, $j, $distM, $newDistM, $uid);

        $pdo->commit();
        return ['moved_m' => $moveM, 'events' => $events];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Hook nach echter Aufgaben-Erledigung (journey.md §2):
 * Ausdauer tanken + Sofort-Distanz (XP → Meter). Der EINZIGE Weg, wie neue
 * Energie ins System kommt.
 */
function journey_on_complete(int $uid, int $xp): ?array
{
    $cfg = journey_cfg();
    if (!journey_enabled() || journey_user_level($uid) < (int) $cfg['unlock_level']) {
        return null;
    }
    ensure_tanuki($uid);

    // Erst die bis jetzt aufgelaufene passive Bewegung abrechnen (eigene Transaktion),
    // damit die frisch getankte Ausdauer nicht rückwirkend Wartezeit vergütet.
    journey_tick($uid);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            "SELECT j.*, (j.boost_until IS NOT NULL AND j.boost_pct > 0 AND j.boost_until > NOW()) AS boost_active
               FROM journeys j
              WHERE j.user_id = ? AND j.status = 'active'
              LIMIT 1
                FOR UPDATE"
        );
        $st->execute([$uid]);
        $j = $st->fetch() ?: null;

        $tp = $pdo->prepare('SELECT * FROM tanuki_profile WHERE user_id = ? FOR UPDATE');
        $tp->execute([$uid]);
        $t = $tp->fetch();

        // Ausdauer tanken — nur bei echtem XP (Termine geben 0 XP → 0 Ausdauer).
        // Ausdauer-Items geben mehr Tank pro Erledigung (multipliziert reale Arbeit).
        $staminaGained = false;
        if ($xp > 0) {
            $old  = max(0, (int) $t['stamina']);
            $max  = (int) ($t['stamina_max'] ?: $cfg['stamina_max_m']);
            $gain = (int) round((int) $cfg['stamina_per_task_m'] * (1 + (float) ($t['stamina_bonus'] ?? 0)));
            $new  = min($old + $gain, $max);
            if ($new !== $old) {
                $pdo->prepare('UPDATE tanuki_profile SET stamina = ? WHERE user_id = ?')->execute([$new, $uid]);
                $staminaGained = true;
            }
        }

        if (!$j) {
            $pdo->commit();
            // Keine aktive Reise: Payload klein halten — nur melden, wenn getankt wurde.
            return $staminaGained ? ['km_added' => 0.0, 'stamina_gained' => true] : null;
        }

        // Sofort-Distanz: XP → Meter. Kein xp_bonus-Faktor mehr: XP-Items boosten
        // jetzt die ECHTEN XP in complete.php (journey_real_bonuses) — die kommen
        // hier bereits verstärkt als $xp an. Doppelt zählen wäre falsch.
        $boostF = ((int) $j['boost_active'] === 1) ? ((int) $j['boost_pct']) / 100 : 0.0;
        $addM   = (int) round($xp * (int) $cfg['m_per_xp'] * (1 + $boostF));

        $capM  = (int) $j['total_km'] * 1000;
        $fromM = (int) $j['distance_m'];
        $toM   = min($fromM + max(0, $addM), $capM);

        $events = [];
        if ($toM > $fromM) {
            $pdo->prepare('UPDATE journeys SET distance_m = ? WHERE id = ?')->execute([$toM, (int) $j['id']]);
            $j['distance_m'] = $toM;
            $events = journey_process_crossings($pdo, $j, $fromM, $toM, $uid);
        }
        $pdo->commit();

        $dn = $pdo->prepare('SELECT name FROM journey_destinations WHERE destination = ?');
        $dn->execute([$j['destination']]);

        return [
            'km_added'     => round(($toM - $fromM) / 1000, 1),
            'remaining_km' => round(max(0, $capM - (int) $j['distance_m']) / 1000, 1),
            'dest_name'    => (string) ($dn->fetchColumn() ?: $j['destination']),
            'arrived'      => ($j['status'] === 'done'),
            'events'       => $events,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Alles abarbeiten, was im Streckenabschnitt (fromM..toM] liegt:
 * Wegpunkte claimen, geplante Events entdecken, Ankunft markieren.
 * 'distance'-Events verlängern die Strecke → while-Schleife (Limit 20).
 * MUSS innerhalb einer offenen Transaktion des Aufrufers laufen.
 */
function journey_process_crossings(PDO $pdo, array &$journey, int $fromM, int $toM, int $uid): array
{
    $cfg   = journey_cfg();
    $capM  = (int) $journey['total_km'] * 1000;
    $jid   = (int) $journey['id'];
    $theme = (string) $journey['destination'];
    $out   = [];

    for ($guard = 0; $guard < 20 && $toM > $fromM; $guard++) {
        // Noch nicht geclaimte Wegpunkte im Abschnitt.
        $wp = $pdo->prepare(
            "SELECT w.* FROM journey_waypoints w
              WHERE w.destination = ? AND w.at_km * 1000 > ? AND w.at_km * 1000 <= ?
                AND NOT EXISTS (SELECT 1 FROM journey_events e
                                 WHERE e.journey_id = ? AND e.kind = 'waypoint' AND e.at_km = w.at_km)
              ORDER BY w.at_km"
        );
        $wp->execute([$theme, $fromM, $toM, $jid]);
        $pending = array_map(static fn(array $w): array => ['_src' => 'waypoint', 'row' => $w], $wp->fetchAll());

        // Vorab gewürfelte, noch unentdeckte Events im Abschnitt.
        $ev = $pdo->prepare(
            'SELECT * FROM journey_events
              WHERE journey_id = ? AND discovered = 0 AND at_km * 1000 > ? AND at_km * 1000 <= ?
              ORDER BY at_km'
        );
        $ev->execute([$jid, $fromM, $toM]);
        foreach ($ev->fetchAll() as $e) {
            $pending[] = ['_src' => 'event', 'row' => $e];
        }
        if (!$pending) {
            break;
        }
        usort($pending, static fn(array $a, array $b): int => ((int) $a['row']['at_km']) <=> ((int) $b['row']['at_km']));

        foreach ($pending as $p) {
            $r      = $p['row'];
            $rType  = $r['reward_type'];
            $amount = (int) ($r['reward_amount'] ?? 0);
            $entry  = [
                'kind'          => $p['_src'] === 'waypoint' ? 'waypoint' : (string) $r['kind'],
                'label'         => (string) ($r['name'] ?? $r['label']),
                'reward_type'   => $rType,
                'reward_amount' => $amount,
            ];

            if ($p['_src'] === 'waypoint') {
                // Claim als Event-Zeile loggen (macht ihn idempotent — NOT EXISTS oben).
                $pdo->prepare(
                    "INSERT INTO journey_events
                            (journey_id, kind, label, reward_type, reward_amount, reward_ref, at_km, discovered, discovered_at)
                     VALUES (?, 'waypoint', ?, ?, ?, ?, ?, 1, NOW())"
                )->execute([$jid, $r['name'], $rType, $amount, $r['reward_ref'], (int) $r['at_km']]);
            } else {
                $pdo->prepare('UPDATE journey_events SET discovered = 1, discovered_at = NOW() WHERE id = ?')
                    ->execute([(int) $r['id']]);
            }

            // Belohnung ausführen.
            if ($rType === 'sparks' && $amount > 0) {
                $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')
                    ->execute([$amount, $uid]);
            } elseif ($rType === 'item') {
                $t = ensure_tanuki($uid);
                $entry['reward'] = journey_grant_item($uid, (string) ($r['reward_ref'] ?: $theme), null, $t['loot_bonus']);
            } elseif ($rType === 'lootbox') {
                $draws = [];
                for ($i = 0; $i < max(1, $amount); $i++) {
                    $draws[] = journey_free_draw($uid, (string) ($r['reward_ref'] ?: $theme));
                }
                $entry['reward'] = count($draws) === 1 ? $draws[0] : $draws;
            } elseif ($rType === 'distance' && $amount > 0) {
                // Rückenwind: schiebt weiter — kann neue Crossings auslösen (deshalb Schleife).
                $journey['distance_m'] = min((int) $journey['distance_m'] + $amount, $capM);
            }
            $out[] = $entry;
        }

        // Strecke durch 'distance'-Events gewachsen? Nächste Runde über den neuen Abschnitt.
        $fromM = $toM;
        $toM   = (int) $journey['distance_m'];
    }

    // Ankunft (journey.md §6): Ziel erreicht → Reise abschließen + Marker.
    if ((int) $journey['distance_m'] >= $capM && $journey['status'] === 'active') {
        $journey['status'] = 'done';
        $dn = $pdo->prepare('SELECT name FROM journey_destinations WHERE destination = ?');
        $dn->execute([$theme]);
        $destName = (string) ($dn->fetchColumn() ?: $theme);
        $pdo->prepare(
            "INSERT INTO journey_events (journey_id, kind, label, reward_type, reward_amount, at_km, discovered, discovered_at)
             VALUES (?, 'arrival', ?, NULL, NULL, ?, 1, NOW())"
        )->execute([$jid, 'Angekommen: ' . $destName, (int) $journey['total_km']]);
        $out[] = ['kind' => 'arrival', 'label' => 'Angekommen: ' . $destName, 'reward_type' => null, 'reward_amount' => 0];
    }

    // Endstand der Reise persistieren (distance kann durch 'distance'-Events gewachsen sein).
    $pdo->prepare(
        "UPDATE journeys SET distance_m = ?, status = ?,
                completed_at = CASE WHEN ? = 'done' THEN COALESCE(completed_at, NOW()) ELSE completed_at END
          WHERE id = ?"
    )->execute([(int) $journey['distance_m'], $journey['status'], $journey['status'], $jid]);

    return $out;
}

/** Item-Rarität würfeln: Basis wie Kosmetik-Gacha, Lootbonus kann 1 Stufe upgraden. */
function journey_roll_rarity(float $lootBonus): string
{
    global $CONFIG;
    $rates = $CONFIG['tuning']['drop_rates'];
    $total = (int) array_sum($rates);
    $r = random_int(1, $total);
    $acc = 0;
    $rolled = array_key_last($rates);
    foreach ($rates as $rarity => $w) {
        $acc += (int) $w;
        if ($r <= $acc) {
            $rolled = $rarity;
            break;
        }
    }
    // Lootbonus: mit Wahrscheinlichkeit min(50 %, Bonus) eine Stufe hoch.
    $idx = (int) array_search($rolled, JOURNEY_RARITY_ORDER, true);
    $up  = min(0.5, max(0.0, $lootBonus));
    if ($idx < count(JOURNEY_RARITY_ORDER) - 1 && $up > 0 && random_int(1, 1000) <= (int) round($up * 1000)) {
        $idx++;
    }
    return JOURNEY_RARITY_ORDER[$idx];
}

/**
 * Reise-Item gutschreiben (journey.md §3, Themen-Treue). Rarität via Lootbonus,
 * Degradierung auf die nächste noch offene Rarität (wie gacha.php draw_cosmetic).
 * Theme komplett? → kleiner Sparks-Trost statt Leerlauf.
 */
function journey_grant_item(int $uid, string $theme, ?string $forcedRarity = null, float $lootBonus = 0.0): ?array
{
    $pdo = db();
    $cfg = journey_cfg();

    // Welche Raritäten haben in diesem Theme noch unbesessene Items?
    $av = $pdo->prepare(
        'SELECT DISTINCT i.rarity FROM items i
          WHERE i.theme = ? AND i.id NOT IN (SELECT item_id FROM user_items WHERE user_id = ?)'
    );
    $av->execute([$theme, $uid]);
    $available = array_column($av->fetchAll(), 'rarity');

    if (!$available) {
        // Alles gesammelt → Sparks-Fallback direkt buchen.
        $fb = (int) $cfg['item_fallback_sparks'];
        $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')->execute([$fb, $uid]);
        return ['fallback_sparks' => $fb];
    }

    $rarity = $forcedRarity ?? journey_roll_rarity($lootBonus);

    // Nächstgelegene noch-offene Rarität (Tie → niedrigere Stufe, wie draw_cosmetic).
    if (!in_array($rarity, $available, true)) {
        $target = (int) array_search($rarity, JOURNEY_RARITY_ORDER, true);
        $best = null;
        $bestDist = 99;
        foreach ($available as $a) {
            $ai = (int) array_search($a, JOURNEY_RARITY_ORDER, true);
            if (abs($ai - $target) < $bestDist
                || (abs($ai - $target) === $bestDist && $ai < (int) array_search($best, JOURNEY_RARITY_ORDER, true))) {
                $best = $a;
                $bestDist = abs($ai - $target);
            }
        }
        $rarity = $best;
    }

    $st = $pdo->prepare(
        'SELECT * FROM items
          WHERE theme = ? AND rarity = ?
            AND id NOT IN (SELECT item_id FROM user_items WHERE user_id = ?)
          ORDER BY RAND() LIMIT 1'
    );
    $st->execute([$theme, $rarity, $uid]);
    $item = $st->fetch();
    if (!$item) {
        // Defensiv (Race): Fallback wie oben.
        $fb = (int) $cfg['item_fallback_sparks'];
        $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')->execute([$fb, $uid]);
        return ['fallback_sparks' => $fb];
    }

    $pdo->prepare('INSERT IGNORE INTO user_items (user_id, item_id) VALUES (?, ?)')->execute([$uid, (int) $item['id']]);

    return [
        'id'        => (int) $item['id'],
        'name'      => $item['name'],
        'type'      => $item['type'],
        'value'     => (float) $item['value'],
        'rarity'    => $item['rarity'],
        'theme'     => $item['theme'],
        'asset_ref' => $item['asset_ref'],
        'flavor'    => $item['flavor'],
    ];
}

/**
 * Gratis-Kosmetik-Zug aus der Themen-Box (journey.md §6) — ohne Sparks-Kosten,
 * ohne Pity-Änderung. Repliziert aus open_lootbox NUR die Gutschrift-Schritte
 * (user_cosmetics-Insert + lootbox_openings-Audit), ändert v1 nicht.
 */
function journey_free_draw(int $uid, string $theme): array
{
    $pdo = db();
    $rarity   = roll_rarity(false);
    $cosmetic = draw_cosmetic($theme, $rarity, $uid);
    if (!$cosmetic) {
        // Theme bereits 100 % → Sparks-Fallback, klar gekennzeichnet.
        $fb = (int) journey_cfg()['arrival_fallback_sparks'];
        $pdo->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')->execute([$fb, $uid]);
        return ['fallback_sparks' => $fb, 'theme_complete' => true];
    }

    $pdo->prepare('INSERT INTO user_cosmetics (user_id, cosmetic_id) VALUES (?, ?)')
        ->execute([$uid, (int) $cosmetic['id']]);

    // Audit wie beim Kauf (kostenlos → refund 0); Box-Referenz best effort.
    $bx = $pdo->prepare('SELECT id FROM lootboxes WHERE theme = ? LIMIT 1');
    $bx->execute([$theme]);
    $boxId = $bx->fetchColumn();
    $pdo->prepare(
        'INSERT INTO lootbox_openings (user_id, lootbox_id, cosmetic_id, was_duplicate, sparks_refund)
         VALUES (?, ?, ?, 0, 0)'
    )->execute([$uid, $boxId !== false ? (int) $boxId : null, (int) $cosmetic['id']]);

    return ['cosmetic' => cosmetic_dto($cosmetic), 'rarity' => $cosmetic['rarity']];
}

/**
 * Reise starten (journey.md §1): ein Ziel aus dem Katalog, immer nur eine aktive
 * Reise (Anti-Overwhelm, §10 Frage 1). Events werden vorab verdeckt gewürfelt.
 */
function journey_start(int $uid, string $destination): array
{
    $cfg = journey_cfg();
    ensure_tanuki($uid);
    $pdo = db();

    $ds = $pdo->prepare('SELECT * FROM journey_destinations WHERE destination = ? AND active = 1');
    $ds->execute([$destination]);
    $dest = $ds->fetch();
    if (!$dest) {
        fail('Dieses Ziel gibt es (noch) nicht.', 404);
    }
    if (journey_user_level($uid) < (int) $dest['unlock_level']) {
        fail("{$dest['name']} schaltet ab Level {$dest['unlock_level']} frei. 🦝", 403);
    }

    $pdo->beginTransaction();
    try {
        // Doppel-Start-Schutz innerhalb der Transaktion (Lock auf evtl. aktive Zeile).
        $act = $pdo->prepare("SELECT id FROM journeys WHERE user_id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
        $act->execute([$uid]);
        if ($act->fetchColumn()) {
            $pdo->rollBack();
            fail('Eine Reise nach der anderen. 🦝', 409);
        }

        $totalKm = (int) $dest['total_km'];
        $pdo->prepare(
            "INSERT INTO journeys (user_id, destination, total_km, distance_m, status, last_tick_at)
             VALUES (?, ?, ?, 0, 'active', NOW())"
        )->execute([$uid, $destination, $totalKm]);
        $jid = (int) $pdo->lastInsertId();

        // Zufalls-Events vorab würfeln (journey.md §5) — Positionen bleiben serverseitig geheim.
        $tpl = $pdo->prepare('SELECT * FROM journey_event_templates WHERE destination = ?');
        $tpl->execute([$destination]);
        $templates = $tpl->fetchAll();
        if ($templates) {
            $minKm = min(5, max(1, $totalKm - 1));
            $maxKm = max($minKm, $totalKm - 5);
            $gap   = (int) $cfg['event_min_gap_km'];
            $totalWeight = 0;
            foreach ($templates as $t) {
                $totalWeight += max(1, (int) $t['weight']);
            }
            $positions = [];
            $ins = $pdo->prepare(
                'INSERT INTO journey_events (journey_id, kind, label, reward_type, reward_amount, at_km, discovered)
                 VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            for ($i = 0; $i < (int) $cfg['event_count']; $i++) {
                // Gewichteter Template-Zug.
                $r = random_int(1, $totalWeight);
                $acc = 0;
                $chosen = $templates[0];
                foreach ($templates as $t) {
                    $acc += max(1, (int) $t['weight']);
                    if ($r <= $acc) {
                        $chosen = $t;
                        break;
                    }
                }
                // Position mit Mindestabstand suchen (60 Versuche, sonst Event auslassen).
                $pos = null;
                for ($try = 0; $try < 60; $try++) {
                    $cand = random_int($minKm, $maxKm);
                    $ok = true;
                    foreach ($positions as $p) {
                        if (abs($p - $cand) < $gap) {
                            $ok = false;
                            break;
                        }
                    }
                    if ($ok) {
                        $pos = $cand;
                        break;
                    }
                }
                if ($pos === null) {
                    continue;
                }
                $positions[] = $pos;
                $amount = random_int((int) $chosen['reward_min'], (int) $chosen['reward_max']);
                $ins->execute([$jid, $chosen['kind'], $chosen['label'], $chosen['reward_type'], $amount, $pos]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $st = $pdo->prepare('SELECT * FROM journeys WHERE id = ?');
    $st->execute([$jid]);
    return $st->fetch();
}

/**
 * Item an-/ablegen (journey.md §3). Max equip_slots; danach die Bonus-Caches
 * am tanuki_profile aus den getragenen Items neu aggregieren.
 */
function journey_equip(int $uid, int $itemId, bool $equip): void
{
    $cfg = journey_cfg();
    ensure_tanuki($uid);
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $own = $pdo->prepare('SELECT equipped FROM user_items WHERE user_id = ? AND item_id = ? FOR UPDATE');
        $own->execute([$uid, $itemId]);
        $row = $own->fetch();
        if (!$row) {
            $pdo->rollBack();
            fail('Dieses Item gehört dir (noch) nicht.', 404);
        }

        if ($equip) {
            $cnt = $pdo->prepare(
                'SELECT COUNT(*) FROM user_items WHERE user_id = ? AND equipped = 1 AND item_id <> ? FOR UPDATE'
            );
            $cnt->execute([$uid, $itemId]);
            if ((int) $cnt->fetchColumn() >= (int) $cfg['equip_slots']) {
                $pdo->rollBack();
                fail('Alle Slots belegt — leg erst etwas ab.', 409);
            }
        }

        $pdo->prepare('UPDATE user_items SET equipped = ? WHERE user_id = ? AND item_id = ?')
            ->execute([$equip ? 1 : 0, $uid, $itemId]);

        // Bonus-Caches neu aggregieren (eine Quelle der Wahrheit: equipped Items).
        $pdo->prepare(
            "UPDATE tanuki_profile tp SET
                tp.speed_bonus = COALESCE((SELECT SUM(i.value) FROM user_items ui JOIN items i ON i.id = ui.item_id
                                            WHERE ui.user_id = tp.user_id AND ui.equipped = 1 AND i.type = 'speed'), 0),
                tp.loot_bonus  = COALESCE((SELECT SUM(i.value) FROM user_items ui JOIN items i ON i.id = ui.item_id
                                            WHERE ui.user_id = tp.user_id AND ui.equipped = 1 AND i.type = 'loot'), 0),
                tp.xp_bonus    = COALESCE((SELECT SUM(i.value) FROM user_items ui JOIN items i ON i.id = ui.item_id
                                            WHERE ui.user_id = tp.user_id AND ui.equipped = 1 AND i.type = 'xp'), 0),
                tp.sparks_bonus  = COALESCE((SELECT SUM(i.value) FROM user_items ui JOIN items i ON i.id = ui.item_id
                                            WHERE ui.user_id = tp.user_id AND ui.equipped = 1 AND i.type = 'sparks'), 0),
                tp.stamina_bonus = COALESCE((SELECT SUM(i.value) FROM user_items ui JOIN items i ON i.id = ui.item_id
                                            WHERE ui.user_id = tp.user_id AND ui.equipped = 1 AND i.type = 'stamina'), 0)
              WHERE tp.user_id = ?"
        )->execute([$uid]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Komplettes State-Payload für den Reise-Tab. */
function journey_state(int $uid): array
{
    $cfg = journey_cfg();
    $pdo = db();
    $t   = ensure_tanuki($uid);
    $lvl = journey_user_level($uid);

    $j = journey_active($uid);

    $journeyDto = null;
    $waypoints  = [];
    $hiddenLeft = 0;
    if ($j) {
        $capM = (int) $j['total_km'] * 1000;
        $dn = $pdo->prepare('SELECT name FROM journey_destinations WHERE destination = ?');
        $dn->execute([$j['destination']]);
        $journeyDto = [
            'destination'  => $j['destination'],
            'name'         => (string) ($dn->fetchColumn() ?: $j['destination']),
            'total_km'     => (int) $j['total_km'],
            'distance_m'   => (int) $j['distance_m'],
            'remaining_km' => round(max(0, $capM - (int) $j['distance_m']) / 1000, 1),
            'pct'          => $capM > 0 ? round((int) $j['distance_m'] / $capM * 100, 1) : 0.0,
            'boost_pct'    => (int) $j['boost_pct'],
            'boost_until'  => $j['boost_until'],
            'status'       => $j['status'],
        ];

        $wp = $pdo->prepare(
            "SELECT w.name, w.at_km, w.reward_type, w.flavor,
                    EXISTS(SELECT 1 FROM journey_events e
                            WHERE e.journey_id = ? AND e.kind = 'waypoint' AND e.at_km = w.at_km) AS claimed
               FROM journey_waypoints w
              WHERE w.destination = ?
              ORDER BY w.at_km"
        );
        $wp->execute([(int) $j['id'], $j['destination']]);
        foreach ($wp->fetchAll() as $w) {
            $waypoints[] = [
                'name'        => $w['name'],
                'at_km'       => (int) $w['at_km'],
                'reward_type' => $w['reward_type'],
                'claimed'     => (bool) $w['claimed'],
                'flavor'      => $w['flavor'],
            ];
        }

        $hl = $pdo->prepare('SELECT COUNT(*) FROM journey_events WHERE journey_id = ? AND discovered = 0');
        $hl->execute([(int) $j['id']]);
        $hiddenLeft = (int) $hl->fetchColumn();
    }

    // Feed: letzte 12 entdeckte Ereignisse über alle Reisen des Users.
    $fd = $pdo->prepare(
        'SELECT e.kind, e.label, e.at_km, e.reward_type, e.reward_amount, e.discovered_at
           FROM journey_events e JOIN journeys j ON j.id = e.journey_id
          WHERE j.user_id = ? AND e.discovered = 1
          ORDER BY e.discovered_at DESC, e.id DESC
          LIMIT 12'
    );
    $fd->execute([$uid]);
    $feed = array_map(static fn(array $e): array => [
        'kind'          => $e['kind'],
        'label'         => $e['label'],
        'at_km'         => $e['at_km'] !== null ? (int) $e['at_km'] : null,
        'reward_type'   => $e['reward_type'],
        'reward_amount' => $e['reward_amount'] !== null ? (int) $e['reward_amount'] : null,
        'discovered_at' => $e['discovered_at'],
    ], $fd->fetchAll());

    // Inventar.
    $inv = $pdo->prepare(
        'SELECT i.id, i.name, i.type, i.value, i.rarity, i.theme, i.asset_ref, i.flavor, ui.equipped
           FROM user_items ui JOIN items i ON i.id = ui.item_id
          WHERE ui.user_id = ?
          ORDER BY ui.equipped DESC, ui.acquired_at DESC'
    );
    $inv->execute([$uid]);
    $items = array_map(static fn(array $i): array => [
        'id'        => (int) $i['id'],
        'name'      => $i['name'],
        'type'      => $i['type'],
        'value'     => (float) $i['value'],
        'rarity'    => $i['rarity'],
        'theme'     => $i['theme'],
        'asset_ref' => $i['asset_ref'],
        'flavor'    => $i['flavor'],
        'equipped'  => (bool) $i['equipped'],
    ], $inv->fetchAll());

    // Ziel-Katalog inkl. „schon bereist".
    $cat = $pdo->prepare(
        "SELECT d.destination, d.name, d.total_km, d.tagline, d.unlock_level,
                (SELECT COUNT(*) FROM journeys jj
                  WHERE jj.user_id = ? AND jj.destination = d.destination AND jj.status = 'done') AS done_count
           FROM journey_destinations d
          WHERE d.active = 1
          ORDER BY d.unlock_level, d.total_km"
    );
    $cat->execute([$uid]);
    $destinations = array_map(static fn(array $d): array => [
        'destination'  => $d['destination'],
        'name'         => $d['name'],
        'total_km'     => (int) $d['total_km'],
        'tagline'      => $d['tagline'],
        'unlock_level' => (int) $d['unlock_level'],
        'unlocked'     => $lvl >= (int) $d['unlock_level'],
        'done'         => ((int) $d['done_count']) > 0,
    ], $cat->fetchAll());

    return [
        'unlocked'           => true,
        'stamina_m'          => $t['stamina'],
        'stamina_max_m'      => $t['stamina_max'],
        'bonuses'            => ['speed' => $t['speed_bonus'], 'loot' => $t['loot_bonus'], 'xp' => $t['xp_bonus'],
                                 'sparks' => $t['sparks_bonus'], 'stamina' => $t['stamina_bonus']],
        'journey'            => $journeyDto,
        'waypoints'          => $waypoints,
        'feed'               => $feed,
        'hidden_events_left' => $hiddenLeft,
        'items'              => $items,
        'equip_slots'        => (int) $cfg['equip_slots'],
        'destinations'       => $destinations,
        'config'             => ['m_per_xp' => (int) $cfg['m_per_xp'], 'passive_kmh' => (float) $cfg['passive_kmh']],
    ];
}

/**
 * Ruhiger Einzeiler für „Heute" (journey.md §8): nur lesen, KEIN Tick —
 * state.php muss schnell bleiben.
 */
function journey_summary(int $uid): ?array
{
    $cfg = journey_cfg();
    if (!journey_enabled() || journey_user_level($uid) < (int) $cfg['unlock_level']) {
        return null;
    }
    $st = db()->prepare(
        "SELECT j.total_km, j.distance_m, COALESCE(d.name, j.destination) AS dest_name
           FROM journeys j
           LEFT JOIN journey_destinations d ON d.destination = j.destination
          WHERE j.user_id = ? AND j.status = 'active'
          LIMIT 1"
    );
    $st->execute([$uid]);
    $j = $st->fetch();
    if (!$j) {
        return null;
    }
    return [
        'dest_name'    => (string) $j['dest_name'],
        'remaining_km' => round(max(0, (int) $j['total_km'] * 1000 - (int) $j['distance_m']) / 1000, 1),
    ];
}
