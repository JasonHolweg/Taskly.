<?php
/**
 * Taskly — „Was jetzt?"-Auswahl (rules.md §5).
 * Harte Filter → Scoring → Rangliste. Eine Aufgabe gewinnt.
 */
declare(strict_types=1);

const ENERGY_MAP = [
    // Task-Energie
    'niedrig' => 1, 'mittel' => 2, 'hoch' => 3,
    // User-Energie (Quick-Select)
    'müde'    => 1, 'ok'     => 2, 'voll' => 3,
    'mude'    => 1,
];

/** Offenen Occurrence-Pool des Users laden (rules.md §5.1, Punkt 1). */
function fetch_pool(int $userId, int $householdId): array
{
    $sql = "
        SELECT o.id AS occ_id, o.scheduled_date, o.status, o.snoozed_until,
               t.id AS task_id, t.title, t.notes, t.type, t.domain,
               t.time_estimate, t.energy, t.context, t.priority, t.base_xp, t.due_at
          FROM task_occurrences o
          JOIN tasks t ON t.id = o.task_id
         WHERE t.household_id = :hh
           AND t.active = 1
           AND t.type IN ('flexible','deadline')
           AND (o.assignee_id = :uid OR o.assignee_id IS NULL)
           AND ( o.status = 'open'
                 OR (o.status = 'snoozed' AND o.snoozed_until IS NOT NULL AND o.snoozed_until < NOW()) )
           AND (o.scheduled_date IS NULL OR o.scheduled_date <= CURDATE())
    ";
    $st = db()->prepare($sql);
    $st->execute([':hh' => $householdId, ':uid' => $userId]);
    return $st->fetchAll();
}

/** Harte Filter anwenden (rules.md §5.1). */
function filter_pool(array $pool, array $ctx, array $skipIds): array
{
    global $CONFIG;
    $time      = (int) ($ctx['time'] ?? 30);
    $tolerance = (float) $CONFIG['tuning']['time_tolerance'];
    $cap       = $time * $tolerance;
    $userCtx   = $ctx['context'] ?? 'egal';

    return array_values(array_filter($pool, function ($o) use ($cap, $skipIds, $userCtx) {
        // 2) Zeit-Cap (mit Toleranz). Unbekannte Dauer passt.
        if ($o['time_estimate'] !== null && (int) $o['time_estimate'] > $cap) {
            return false;
        }
        // 3) Kontext: unterwegs → keine reinen Zuhause-Tasks
        if ($userCtx === 'unterwegs' && $o['context'] === 'zuhause') {
            return false;
        }
        // 4) nicht in der Skip-Liste dieser Session
        if (in_array((int) $o['occ_id'], $skipIds, true)) {
            return false;
        }
        return true;
    }));
}

/** Eine Occurrence bewerten (rules.md §5.2). */
function score_occurrence(array $o, array $ctx, array $recentIds): float
{
    global $CONFIG;
    $w = $CONFIG['tuning']['select_weights'];

    $userEnergy = ENERGY_MAP[$ctx['energy'] ?? 'ok'] ?? 2;
    $taskEnergy = ENERGY_MAP[$o['energy'] ?? 'mittel'] ?? 2;

    // w1: Energie-Match (kleiner Abstand = hoch)
    $energyMatch = 1.0 - abs($userEnergy - $taskEnergy) / 2.0;

    // w2: Deadline-Nähe (eskalierend; überfällig = max)
    $deadlineNear = 0.0;
    if ($o['type'] === 'deadline' && !empty($o['due_at'])) {
        $days = (new DateTimeImmutable('now'))->diff(new DateTimeImmutable($o['due_at']));
        $signed = ($days->invert ? -1 : 1) * (int) $days->days;
        $deadlineNear = $signed <= 0 ? 1.0 : 1.0 / (1.0 + $signed);
    }

    // w3: Priorität (1..3 → 0..1)
    $priority = ((int) ($o['priority'] ?? 2)) / 3.0;

    // w4: Tageszeit-Eignung (grobe Heuristik)
    $hour = (int) date('G');
    $timeFit = 1.0;
    if ($hour >= 21 && $taskEnergy === 3) {
        $timeFit = 0.3; // spätabends nichts Schweres
    } elseif ($hour <= 9 && $taskEnergy === 3) {
        $timeFit = 1.0; // morgens gern das Dicke
    }

    // w5: Wiederholungs-Malus (kürzlich vorgeschlagen)
    $repeatMalus = in_array((int) $o['occ_id'], $recentIds, true) ? 1.0 : 0.0;

    // w6: Quick-Win-Bonus — nur wenn müde → kurze Tasks
    $quickWin = 0.0;
    if ($userEnergy === 1 && $o['time_estimate'] !== null && (int) $o['time_estimate'] <= 15) {
        $quickWin = 1.0;
    }

    return $w['w1'] * $energyMatch
         + $w['w2'] * $deadlineNear
         + $w['w3'] * $priority
         + $w['w4'] * $timeFit
         - $w['w5'] * $repeatMalus
         + $w['w6'] * $quickWin;
}

/**
 * Rangliste der gefilterten Occurrences (rules.md §5.2/§5.3).
 * Tie-Break: näheste Deadline, dann älteste Occurrence.
 */
function rank_pool(array $pool, array $ctx, array $skipIds, array $recentIds = []): array
{
    $filtered = filter_pool($pool, $ctx, $skipIds);
    foreach ($filtered as &$o) {
        $o['score'] = score_occurrence($o, $ctx, $recentIds);
    }
    unset($o);

    usort($filtered, function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        // Tie-Break 1: näheste Deadline
        $da = $a['due_at'] ? strtotime($a['due_at']) : PHP_INT_MAX;
        $db = $b['due_at'] ? strtotime($b['due_at']) : PHP_INT_MAX;
        if ($da !== $db) {
            return $da <=> $db;
        }
        // Tie-Break 2: älteste Occurrence
        return (int) $a['occ_id'] <=> (int) $b['occ_id'];
    });

    return $filtered;
}

/** Begründungs-Satz für den Pick (rules.md §5.3 / §7). */
function reason_for(array $pick, array $ctx): string
{
    $energy = $ctx['energy'] ?? 'ok';
    $min    = (int) ($pick['time_estimate'] ?? 0);
    $xp     = (int) ($pick['base_xp'] ?? 0);
    $vars   = ['task' => $pick['title'], 'min' => $min, 'xp' => $xp];

    if ($pick['type'] === 'deadline' && !empty($pick['due_at'])) {
        return copy_line('whatnow_deadline', $vars);
    }
    if ($energy === 'müde' || $energy === 'mude') {
        return copy_line('whatnow_tired', $vars);
    }
    if ($energy === 'voll') {
        return copy_line('whatnow_full', $vars);
    }
    return copy_line('whatnow_ok', $vars);
}
