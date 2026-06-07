<?php
/**
 * Taskly — Nudge-Cron (architecture.md §5). Alle 5 Min:
 *  1) Termin-Reminder X Min vor fixed_at
 *  2) „Was jetzt?"-Nudges zu den gewählten Zeitfenstern (eine konkrete Aufgabe)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur via CLI.\n");
}
require __DIR__ . '/../src/bootstrap.php';

$pdo       = db();
$leadMin   = 30;   // Termin-Vorlauf
$windowTol = 5;    // Cron-Intervall (Minuten)
$sentTermin = 0; $sentIdle = 0;

// ---------- 1) Termin-Reminder ----------
$st = $pdo->prepare(
    "SELECT o.id AS occ_id, o.assignee_id, t.title, t.fixed_at
       FROM task_occurrences o
       JOIN tasks t ON t.id = o.task_id
      WHERE t.type = 'termin' AND t.active = 1 AND o.status = 'open'
        AND t.fixed_at IS NOT NULL
        AND t.fixed_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
        AND NOT EXISTS (
              SELECT 1 FROM nudges n
               WHERE n.occurrence_id = o.id AND n.template = 'termin_reminder'
            )"
);
$st->execute([$leadMin]);
foreach ($st->fetchAll() as $o) {
    $uid = (int) ($o['assignee_id'] ?? 0);
    if (!$uid) {
        continue;
    }
    $time = date('H:i', strtotime($o['fixed_at']));
    if (send_push($uid, '📌 Gleich: ' . $o['title'], "Um $time. Denk dran!", '/')) {
        $sentTermin++;
    }
    log_nudge($uid, (int) $o['occ_id'], 'termin_reminder');
}

// ---------- 2) Idle-/„Was jetzt?"-Nudges ----------
$nowMin = (int) date('G') * 60 + (int) date('i');

$users = $pdo->query(
    "SELECT u.id, u.household_id, u.nudge_prefs
       FROM users u
      WHERE u.push_sub IS NOT NULL AND u.nudge_prefs IS NOT NULL"
)->fetchAll();

$dedup = $pdo->prepare(
    "SELECT 1 FROM nudges
      WHERE user_id = ? AND template = 'idle' AND sent_at > DATE_SUB(NOW(), INTERVAL 3 HOUR) LIMIT 1"
);

foreach ($users as $u) {
    $prefs = json_decode((string) $u['nudge_prefs'], true);
    if (!$prefs || empty($prefs['enabled']) || empty($prefs['windows'])) {
        continue;
    }
    // Fällt jetzt in ein Zeitfenster (innerhalb des Cron-Intervalls)?
    $hit = false;
    foreach ($prefs['windows'] as $w) {
        if (!preg_match('/^(\d{2}):(\d{2})$/', (string) $w, $m)) {
            continue;
        }
        $winMin = (int) $m[1] * 60 + (int) $m[2];
        if ($nowMin >= $winMin && $nowMin < $winMin + $windowTol) {
            $hit = true; break;
        }
    }
    if (!$hit) {
        continue;
    }

    $uid = (int) $u['id'];
    $dedup->execute([$uid]);
    if ($dedup->fetchColumn()) {
        continue; // in den letzten 3h schon genudged
    }

    // Eine konkrete Aufgabe wählen (rules.md §5)
    $pool = fetch_pool($uid, (int) $u['household_id']);
    $ranked = rank_pool($pool, ['time' => 30, 'energy' => 'ok', 'context' => 'egal'], [], []);
    if (!$ranked) {
        continue; // nichts offen → kein Nudge (kein Listen-Guilt)
    }
    $p = $ranked[0];
    $min = (int) $p['time_estimate']; $xp = (int) $p['base_xp'];
    if (send_push($uid, 'Taskly 🦝', "Hast du $min Min? {$p['title']} — $xp XP 💪", '/')) {
        $sentIdle++;
    }
    log_nudge($uid, (int) $p['occ_id'], 'idle');
}

printf("[%s] Nudges: %d Termin, %d Idle\n", date('c'), $sentTermin, $sentIdle);
