<?php
/**
 * Taskly — Akzeptanz- & Guardrail-Tests für „Tanuki's Adventures" (v2).
 * build-Brief §7: eiserne Regel, Task-Antrieb, Gates, Spielbarkeit, Themen-Treue.
 * Läuft NUR via CLI auf dem Server (echte DB, eigene Wegwerf-Daten, räumt auf).
 * Aufruf:  php bin/test_journey.php   → Exit 0 = alles grün.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur via CLI.\n");
}

require __DIR__ . '/../src/bootstrap.php';

$pdo  = db();
$pass = 0;
$failCnt = 0;

function ok(bool $cond, string $label): void
{
    global $pass, $failCnt;
    if ($cond) { $pass++; echo "  ✅ $label\n"; }
    else       { $failCnt++; echo "  ❌ $label\n"; }
}

echo "Tanuki's Adventures — Guardrail-Tests\n=====================================\n";

// ---------- Setup: Wegwerf-User (NIE echte Accounts anfassen) ----------
$pdo->prepare("INSERT INTO households (name, invite_code) VALUES ('JNY-Test', SUBSTRING(MD5(RAND()),1,8))")->execute();
$hh = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (household_id, name, email, password_hash) VALUES (?, 'JnyTest', CONCAT('jny-test-', UUID(), '@example.com'), 'x')")->execute([$hh]);
$uid = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO user_progress (user_id, xp_total, level, sparks) VALUES (?, 250, 3, 0)')->execute([$uid]); // exakt Level 3
$pdo->prepare('INSERT INTO tanuki_profile (user_id) VALUES (?)')->execute([$uid]);
echo "Setup: User #$uid (Level 3, @example.com)\n\n";

try {
    $cfg = journey_cfg();

    // ---------- T1: Flag & Defaults ----------
    echo "T1 Feature-Flag/Config\n";
    ok(journey_enabled() === true, 'journey_enabled() default true (Server-Config darf Key weglassen)');
    ok((int) $cfg['m_per_xp'] === 100 && (int) $cfg['stamina_max_m'] === 12000, 'Stellschrauben-Defaults (1 XP = 100 m, max 12 km)');

    // ---------- T2: Content seeded & spielbar (DoD „Spielbar bei L3") ----------
    echo "T2 Content\n";
    $dests = $pdo->query("SELECT * FROM journey_destinations WHERE active = 1")->fetchAll();
    ok(count($dests) >= 3, 'mindestens 3 aktive Ziele (' . count($dests) . ')');
    foreach ($dests as $d) {
        $t = $d['destination'];
        $items = $pdo->prepare('SELECT rarity, COUNT(*) c FROM items WHERE theme = ? GROUP BY rarity');
        $items->execute([$t]);
        $byRar = array_column($items->fetchAll(), 'c', 'rarity');
        $total = array_sum($byRar);
        ok($total >= 12 && count($byRar) === 4, "Theme '$t': ≥12 Items über alle 4 Raritäten ($total)");
        $wp = $pdo->prepare("SELECT COUNT(*) FROM journey_waypoints WHERE destination = ?");
        $wp->execute([$t]);
        $goal = $pdo->prepare("SELECT reward_type FROM journey_waypoints WHERE destination = ? AND at_km = ?");
        $goal->execute([$t, (int) $d['total_km']]);
        ok((int) $wp->fetchColumn() >= 6 && $goal->fetchColumn() === 'lootbox', "Theme '$t': ≥6 Wegpunkte, Ziel = Themen-Box");
        $ev = $pdo->prepare('SELECT COUNT(*) FROM journey_event_templates WHERE destination = ?');
        $ev->execute([$t]);
        ok((int) $ev->fetchColumn() >= 5, "Theme '$t': ≥5 Event-Vorlagen");
    }

    // ---------- T3: Reise starten ----------
    echo "T3 Reise-Start\n";
    journey_start($uid, 'japan');
    $j = journey_active($uid);
    ok($j !== null && (int) $j['distance_m'] === 0, 'Reise aktiv, Distanz 0');
    $planned = $pdo->prepare('SELECT COUNT(*) FROM journey_events WHERE journey_id = ? AND discovered = 0');
    $planned->execute([(int) $j['id']]);
    ok((int) $planned->fetchColumn() >= 1, 'versteckte Events vorab gewürfelt');

    // ---------- T4: EISERNE REGEL — Warten ohne Aufgaben bewegt NICHTS ----------
    echo "T4 Eiserne Regel (§0)\n";
    $pdo->prepare('UPDATE journeys SET last_tick_at = DATE_SUB(NOW(), INTERVAL 10 HOUR) WHERE id = ?')->execute([(int) $j['id']]);
    $r = journey_tick($uid);
    $j = journey_active($uid);
    ok($r['moved_m'] === 0 && (int) $j['distance_m'] === 0, '10 h Wartezeit + 0 Ausdauer → 0 m Bewegung');
    $tick = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, last_tick_at, NOW()) FROM journeys WHERE id = ?');
    $tick->execute([(int) $j['id']]);
    ok((int) $tick->fetchColumn() < 10, 'last_tick_at trotzdem aktualisiert (keine rückwirkende Vergütung)');

    // ---------- T5: Task-Antrieb (XP → Meter + Ausdauer) ----------
    echo "T5 Task-Antrieb\n";
    $res = journey_on_complete($uid, 40);   // 40 XP → 4000 m
    $j = journey_active($uid);
    $t = ensure_tanuki($uid);
    ok((int) $j['distance_m'] === 4000, '40 XP → exakt 4,0 km Sofort-Distanz (' . $j['distance_m'] . ' m)');
    ok((int) $t['stamina'] === 1000, '+1 km Ausdauer getankt');
    ok(abs($res['km_added'] - 4.0) < 0.01, 'Hook-Payload km_added = 4,0');

    // ---------- T6/T7: Passive Bewegung — durch Ausdauer gedeckelt ----------
    echo "T6/T7 Passive Bewegung\n";
    $pdo->prepare('UPDATE journeys SET last_tick_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?')->execute([(int) $j['id']]);
    $r = journey_tick($uid);
    ok(abs($r['moved_m'] - 500) <= 5, '30 Min × 1 km/h ≈ 500 m passive Bewegung (' . $r['moved_m'] . ')');
    $pdo->prepare('UPDATE journeys SET last_tick_at = DATE_SUB(NOW(), INTERVAL 10 HOUR) WHERE id = ?')->execute([(int) $j['id']]);
    $r = journey_tick($uid);
    $t = ensure_tanuki($uid);
    ok($r['moved_m'] <= 505 && (int) $t['stamina'] === 0, '10 h Wartezeit bewegt nur die RESTLICHE Ausdauer, dann Stopp');
    ok((int) $t['stamina'] >= 0, 'Ausdauer nie negativ');

    // ---------- T8: Ausdauer-Cap ----------
    echo "T8 Ausdauer-Cap\n";
    for ($i = 0; $i < 14; $i++) { journey_on_complete($uid, 1); }
    $t = ensure_tanuki($uid);
    ok((int) $t['stamina'] <= 12000, 'Ausdauer gedeckelt bei 12 km (' . $t['stamina'] . ' m)');

    // ---------- T9: Wegpunkt-Crossing + Belohnung + Themen-Treue ----------
    echo "T9 Wegpunkte & Themen-Treue\n";
    $j = journey_active($uid);
    $wp = $pdo->query("SELECT * FROM journey_waypoints WHERE destination = 'japan' AND reward_type = 'item' ORDER BY at_km LIMIT 1")->fetch();
    if ($wp) {
        $target = ((int) $wp['at_km']) * 1000 - 100;
        $pdo->prepare('UPDATE journeys SET distance_m = ? WHERE id = ?')->execute([$target, (int) $j['id']]);
        journey_on_complete($uid, 10); // +1000 m → kreuzt den Wegpunkt
        $claimed = $pdo->prepare("SELECT COUNT(*) FROM journey_events WHERE journey_id = ? AND kind = 'waypoint' AND at_km = ?");
        $claimed->execute([(int) $j['id'], (int) $wp['at_km']]);
        ok((int) $claimed->fetchColumn() === 1, 'Item-Wegpunkt geclaimt (idempotent geloggt)');
        $inv = $pdo->prepare("SELECT COUNT(*) FROM user_items ui JOIN items i ON i.id = ui.item_id WHERE ui.user_id = ? AND i.theme = 'japan'");
        $inv->execute([$uid]);
        $invOther = $pdo->prepare("SELECT COUNT(*) FROM user_items ui JOIN items i ON i.id = ui.item_id WHERE ui.user_id = ? AND i.theme <> 'japan'");
        $invOther->execute([$uid]);
        ok((int) $inv->fetchColumn() >= 1 && (int) $invOther->fetchColumn() === 0, 'gefundenes Item gehört zum Reise-Thema (japan)');
    } else {
        ok(false, 'kein Item-Wegpunkt in japan gefunden (Seed prüfen)');
    }

    // ---------- T10: Equip + xp_bonus wirkt NUR auf Distanz ----------
    echo "T10 Items & Guardrail xp_bonus\n";
    $item = $pdo->query("SELECT id, value FROM items WHERE theme = 'japan' AND type = 'xp' ORDER BY value DESC LIMIT 1")->fetch();
    $pdo->prepare('INSERT IGNORE INTO user_items (user_id, item_id) VALUES (?, ?)')->execute([$uid, (int) $item['id']]);
    journey_equip($uid, (int) $item['id'], true);
    $t = ensure_tanuki($uid);
    ok((float) $t['xp_bonus'] > 0, 'xp_bonus-Cache nach Equip > 0 (+' . $t['xp_bonus'] . ')');
    $xpBefore = (int) $pdo->query("SELECT xp_total FROM user_progress WHERE user_id = $uid")->fetchColumn();
    $j = journey_active($uid);
    $dBefore = (int) $j['distance_m'];
    journey_on_complete($uid, 10);
    $xpAfter = (int) $pdo->query("SELECT xp_total FROM user_progress WHERE user_id = $uid")->fetchColumn();
    $j = journey_active($uid);
    $expected = (int) round(10 * 100 * (1 + (float) $t['xp_bonus']));
    ok($xpAfter === $xpBefore, 'user_progress.xp_total von der Engine UNBERÜHRT (eiserne Regel §3)');
    ok(((int) $j['distance_m'] - $dBefore) === $expected, "xp_bonus wirkt nur auf Distanz (+$expected m)");

    // ---------- T11: Ankunft → Themen-Box-Gratiszug ----------
    echo "T11 Ankunft\n";
    $j = journey_active($uid);
    $capM = (int) $j['total_km'] * 1000;
    $pdo->prepare('UPDATE journeys SET distance_m = ? WHERE id = ?')->execute([$capM - 200, (int) $j['id']]);
    $cosBefore = (int) $pdo->query("SELECT COUNT(*) FROM user_cosmetics WHERE user_id = $uid")->fetchColumn();
    $sparksBefore = (int) $pdo->query("SELECT sparks FROM user_progress WHERE user_id = $uid")->fetchColumn();
    $res = journey_on_complete($uid, 30);
    $done = $pdo->prepare("SELECT status, completed_at FROM journeys WHERE id = ?");
    $done->execute([(int) $j['id']]);
    $dj = $done->fetch();
    $cosAfter = (int) $pdo->query("SELECT COUNT(*) FROM user_cosmetics WHERE user_id = $uid")->fetchColumn();
    $sparksAfter = (int) $pdo->query("SELECT sparks FROM user_progress WHERE user_id = $uid")->fetchColumn();
    ok($dj['status'] === 'done' && $dj['completed_at'] !== null, 'Reise abgeschlossen (status done)');
    ok($res['arrived'] === true, 'Hook meldet arrived');
    ok($cosAfter > $cosBefore || $sparksAfter > $sparksBefore, 'Ziel-Box: Kosmetik gezogen ODER Sparks-Fallback (Theme komplett)');
    ok(journey_summary($uid) === null, 'Summary nach Ankunft wieder null (Heute-Zeile verschwindet)');

    // ---------- T12: Gates (Logik-Ebene) ----------
    echo "T12 Gates\n";
    $pdo->prepare('UPDATE user_progress SET xp_total = 0 WHERE user_id = ?')->execute([$uid]);
    ok(journey_user_level($uid) < (int) $cfg['unlock_level'], 'Level 1 < Unlock-Level');
    ok(journey_on_complete($uid, 40) === null, 'Hook unter Level 3 → null (keine Reise-Effekte)');
    $pdo->prepare('UPDATE user_progress SET xp_total = 250 WHERE user_id = ?')->execute([$uid]);
    ok(journey_user_level($uid) >= 3, 'Level 3 nach XP-Anhebung (Erscheinen bei Level-Up)');

    // ---------- T13: keine LLM-Calls in der Engine ----------
    echo "T13 Kosten\n";
    $src = file_get_contents(__DIR__ . '/../src/lib/journey.php');
    ok(strpos($src, 'claude_call') === false && strpos($src, 'anthropic') === false, 'journey.php enthält keine LLM-Aufrufe');
} catch (Throwable $e) {
    $failCnt++;
    echo "  ❌ EXCEPTION: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
} finally {
    // ---------- Cleanup (nur eigene Wegwerf-Daten) ----------
    $pdo->prepare('DELETE FROM users WHERE id = ? AND email LIKE ?')->execute([$uid, '%@example.com']);
    $pdo->prepare('DELETE FROM households WHERE id = ?')->execute([$hh]);
    echo "\nCleanup: User #$uid + Haushalt #$hh gelöscht.\n";
}

echo "\nErgebnis: $pass bestanden, $failCnt fehlgeschlagen.\n";
exit($failCnt === 0 ? 0 : 1);
