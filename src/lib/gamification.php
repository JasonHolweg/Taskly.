<?php
/**
 * Taskly — Gamification-Logik (rules.md §1–§4).
 * XP, Level-Kurve, Streaks, Glücksumschläge.
 */
declare(strict_types=1);

/** Zeit-Basis-XP für eine Minutenzahl (rules.md §1). */
function xp_time_base(int $minutes): int
{
    global $CONFIG;
    $table = $CONFIG['tuning']['xp_time_base']; // [5=>10,15=>20,30=>40,999=>60]
    ksort($table);
    foreach ($table as $cap => $base) {
        if ($minutes <= $cap) {
            return (int) $base;
        }
    }
    return (int) end($table);
}

/** base_xp aus Zeit × Widerstand, gerundet auf 5er (rules.md §1). */
function compute_base_xp(int $minutes, string $resistance): int
{
    global $CONFIG;
    $factors = $CONFIG['tuning']['xp_resistance'];
    $factor  = $factors[$resistance] ?? $factors['neutral'];
    $raw     = xp_time_base($minutes) * $factor;
    $round   = (int) $CONFIG['tuning']['xp_round_to'];
    return (int) (round($raw / $round) * $round);
}

/** XP nötig für den Sprung level → level+1 (rules.md §2). */
function xp_for_next(int $level): int
{
    global $CONFIG;
    return (int) ($CONFIG['tuning']['level_base'] + $CONFIG['tuning']['level_step'] * ($level - 1));
}

/** Level + Fortschritt aus xp_total ableiten. */
function level_from_xp(int $xpTotal): array
{
    $level = 1;
    $remaining = $xpTotal;
    while ($remaining >= xp_for_next($level)) {
        $remaining -= xp_for_next($level);
        $level++;
    }
    return [
        'level'        => $level,
        'xp_into'      => $remaining,            // XP im aktuellen Level
        'xp_needed'    => xp_for_next($level),   // XP bis zum nächsten
    ];
}

/** user_progress laden (legt Default-Zeile an, falls fehlt). */
function get_progress(int $userId): array
{
    $row = db()->prepare('SELECT * FROM user_progress WHERE user_id = ?');
    $row->execute([$userId]);
    $p = $row->fetch();
    if (!$p) {
        db()->prepare('INSERT INTO user_progress (user_id) VALUES (?)')->execute([$userId]);
        $row->execute([$userId]);
        $p = $row->fetch();
    }
    return $p;
}

/** Glücksumschlag erzeugen + sofort öffnen → Sparks gutschreiben. */
function grant_envelope(int $userId, string $source, int $sparks): int
{
    db()->prepare(
        'INSERT INTO envelopes (user_id, source, sparks_amount, opened_at) VALUES (?, ?, ?, NOW())'
    )->execute([$userId, $source, $sparks]);
    db()->prepare('UPDATE user_progress SET sparks = sparks + ? WHERE user_id = ?')
        ->execute([$sparks, $userId]);
    return $sparks;
}

/**
 * Streak nach einer Erledigung fortschreiben (rules.md §4).
 * Sanftes Modell: Schontag-Puffer fängt einen verpassten Tag ab.
 * Gibt erreichte Meilenstein-Sparks zurück (0 wenn keiner).
 */
function advance_streak(int $userId, array $p): array
{
    global $CONFIG;
    $today = new DateTimeImmutable('today');
    $last  = $p['streak_last'] ? new DateTimeImmutable($p['streak_last']) : null;

    $streak    = (int) $p['streak_count'];
    $longest   = (int) $p['longest_streak'];
    $schontag  = (bool) $p['schontag_available'];
    $milestone = 0;

    if ($last === null) {
        $streak = 1;
    } else {
        $diff = (int) $last->diff($today)->days;
        if ($diff === 0) {
            // heute schon gezählt → nichts ändern
            return ['streak' => $streak, 'milestone_sparks' => 0, 'changed' => false];
        } elseif ($diff === 1) {
            $streak += 1;
        } elseif ($diff === 2 && $schontag) {
            // Puffer-Tag fängt den einen verpassten Tag lautlos ab
            $streak += 1;
            $schontag = false;
        } else {
            // Bruch: Reset auf 1 (longest bleibt als „du kannst das")
            $streak = 1;
        }
    }

    $longest = max($longest, $streak);

    // Schontag: ab Streak ≥ 3 verfügbar, lädt nach N zusammenhängenden Tagen nach
    if ($streak >= 3 && ($streak % $CONFIG['tuning']['schontag_recharge'] === 0 || !$schontag && $streak === 3)) {
        $schontag = true;
    }

    // Meilenstein-Boni (3/7/14/30)
    $bonus = $CONFIG['tuning']['streak_bonus'][$streak] ?? 0;
    if ($bonus > 0) {
        $milestone = grant_envelope($userId, 'streak_milestone', (int) $bonus);
    }

    db()->prepare(
        'UPDATE user_progress
            SET streak_count = ?, streak_last = ?, longest_streak = ?, schontag_available = ?
          WHERE user_id = ?'
    )->execute([$streak, $today->format('Y-m-d'), $longest, $schontag ? 1 : 0, $userId]);

    return ['streak' => $streak, 'milestone_sparks' => $milestone, 'changed' => true];
}

/**
 * Eine Occurrence als erledigt verbuchen und alle Belohnungen anwenden.
 * Gibt ein „reward"-Paket fürs Frontend zurück.
 */
function complete_occurrence(int $userId, array $occ, array $task): array
{
    global $CONFIG;
    $pdo = db();

    // Termine geben kein XP (rules.md §1)
    $xp = ($task['type'] === 'termin') ? 0 : (int) ($task['base_xp'] ?? 0);

    $pdo->prepare(
        "UPDATE task_occurrences
            SET status = 'done', awarded_xp = ?, completed_at = NOW()
          WHERE id = ? AND status != 'done'"
    )->execute([$xp, $occ['id']]);

    $before = get_progress($userId);
    $lvlBefore = level_from_xp((int) $before['xp_total']);

    $pdo->prepare('UPDATE user_progress SET xp_total = xp_total + ? WHERE user_id = ?')
        ->execute([$xp, $userId]);

    $after = get_progress($userId);
    $lvlAfter = level_from_xp((int) $after['xp_total']);

    $rewards = [
        'xp'         => $xp,
        'message'    => copy_line('done', ['xp' => $xp]),
        'leveled_up' => false,
        'envelopes'  => [],
    ];

    // Level-Up → Glücksumschlag je gewonnenem Level (rules.md §2/§3)
    if ($lvlAfter['level'] > $lvlBefore['level']) {
        $rewards['leveled_up'] = true;
        [$min, $max] = $CONFIG['tuning']['levelup_sparks'];
        for ($l = $lvlBefore['level'] + 1; $l <= $lvlAfter['level']; $l++) {
            $sparks = random_int((int) $min, (int) $max);
            grant_envelope($userId, 'levelup', $sparks);
            $rewards['envelopes'][] = ['source' => 'levelup', 'sparks' => $sparks, 'level' => $l];
        }
        $rewards['level']        = $lvlAfter['level'];
        $rewards['level_message'] = copy_line('levelup', ['n' => $lvlAfter['level']]);
    }

    // Streak fortschreiben
    $streak = advance_streak($userId, $after);
    $rewards['streak'] = $streak['streak'];
    if ($streak['milestone_sparks'] > 0) {
        $rewards['envelopes'][] = ['source' => 'streak_milestone', 'sparks' => $streak['milestone_sparks']];
    }

    return $rewards;
}
