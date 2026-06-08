<?php
/**
 * Taskly — Wochen-Verteilung (architecture.md §4.2, rules.md §6).
 * „Die KI plant, nicht der User." Haiku verteilt offene Tasks über die Woche;
 * deterministischer PHP-Fallback sichert Robustheit.
 */
declare(strict_types=1);

/** Die 7 Tage der Planungswoche ab heute (Y-m-d). */
function week_days(?DateTimeImmutable $start = null): array
{
    $start = $start ?? new DateTimeImmutable('today');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = $start->modify("+$i day")->format('Y-m-d');
    }
    return $days;
}

/**
 * Wiederkehrende Tasks für die Woche materialisieren: pro RRULE-Termin eine
 * offene Occurrence anlegen, falls für (task, Datum) noch keine existiert.
 */
function ensure_recurrences(int $userId, ?string $weekStart = null): void
{
    $pdo  = db();
    $start = new DateTimeImmutable($weekStart ?? 'today');
    $end   = $start->modify('+6 day');

    $st = $pdo->prepare(
        "SELECT t.id, t.recurrence_rule, t.created_at
           FROM tasks t
          WHERE t.created_by = ? AND t.active = 1
            AND t.type IN ('flexible','deadline')
            AND t.recurrence_rule IS NOT NULL AND t.recurrence_rule <> ''"
    );
    $st->execute([$userId]);
    $tasks = $st->fetchAll();
    if (!$tasks) {
        return;
    }

    $exists = $pdo->prepare(
        'SELECT 1 FROM task_occurrences WHERE task_id = ? AND scheduled_date = ? LIMIT 1'
    );
    $ins = $pdo->prepare(
        "INSERT INTO task_occurrences (task_id, assignee_id, scheduled_date, status)
         VALUES (?, ?, ?, 'open')"
    );

    foreach ($tasks as $t) {
        $anchor = $t['created_at'] ? new DateTimeImmutable($t['created_at']) : $start;
        foreach (expand_rrule($t['recurrence_rule'], $start, $end, $anchor) as $date) {
            $exists->execute([$t['id'], $date]);
            if (!$exists->fetchColumn()) {
                $ins->execute([$t['id'], $userId, $date]);
            }
        }
    }
}

/** Planbarer Pool: offene, NICHT wiederkehrende flexible/deadline-Occurrences,
 *  die ungeplant (NULL) oder innerhalb/vor dieser Woche liegen → neu verteilbar. */
function plannable_pool(int $userId, ?string $weekStart = null): array
{
    $end = (new DateTimeImmutable($weekStart ?? 'today'))->modify('+6 day')->format('Y-m-d');
    $st = db()->prepare(
        "SELECT o.id AS occ_id, o.scheduled_date,
                t.title, t.type, t.domain, t.time_estimate, t.energy, t.priority, t.due_at
           FROM task_occurrences o
           JOIN tasks t ON t.id = o.task_id
          WHERE (o.assignee_id = :uid OR o.assignee_id IS NULL)
            AND o.status = 'open'
            AND t.active = 1
            AND t.type IN ('flexible','deadline')
            AND (t.recurrence_rule IS NULL OR t.recurrence_rule = '')
            AND (o.scheduled_date IS NULL OR o.scheduled_date <= :end)
          ORDER BY (t.type='deadline') DESC, t.due_at IS NULL, t.due_at, t.priority DESC"
    );
    $st->execute([':uid' => $userId, ':end' => $end]);
    return $st->fetchAll();
}

/** Ist die Occurrence „schwer" (rules.md §6: 60+ Min oder hoch-Energie)? */
function occ_is_heavy(array $o): bool
{
    return ((int) $o['time_estimate'] >= 60) || ($o['energy'] === 'hoch');
}

/** Deterministische Verteilung (Fallback + Sicherheitsnetz für die KI). */
function php_distribute(array $pool, array $days): array
{
    global $CONFIG;
    $max   = (int) $CONFIG['tuning']['plan_max_per_day'];
    $count = array_fill_keys($days, 0);
    $heavy = array_fill_keys($days, 0);
    $doms  = array_fill_keys($days, []);
    $today = $days[0];

    $assign = [];
    foreach ($pool as $o) {
        // Kandidaten-Tage (Deadline: nur bis zum Fälligkeitstag)
        $cands = $days;
        if ($o['type'] === 'deadline' && !empty($o['due_at'])) {
            $due = substr($o['due_at'], 0, 10);
            $cands = array_values(array_filter($days, fn($d) => $d <= $due));
            if (!$cands) {
                $cands = [$today]; // überfällig → heute sanft nach vorne
            }
        }
        // besten Tag wählen: passt-in-Cap → wenig belegt → Domain-Mix → früh
        $best = null; $bestScore = PHP_INT_MAX;
        foreach ($cands as $d) {
            $fits  = $count[$d] < $max && (!occ_is_heavy($o) || $heavy[$d] < 1);
            $score = ($fits ? 0 : 1000)
                   + $count[$d] * 10
                   + (in_array($o['domain'], $doms[$d], true) ? 3 : 0);
            if ($score < $bestScore) {
                $bestScore = $score; $best = $d;
            }
        }
        $assign[(int) $o['occ_id']] = $best;
        $count[$best]++;
        if (occ_is_heavy($o)) {
            $heavy[$best]++;
        }
        $doms[$best][] = $o['domain'];
    }
    return $assign;
}

/** Verteilung via Haiku (rules.md §6), mit PHP-Fallback für Lücken/Fehler. */
function ai_distribute(array $pool, array $days): array
{
    global $CONFIG;
    if (!$pool) {
        return [];
    }
    $fallback = php_distribute($pool, $days);
    if (!claude_available()) {
        return $fallback;
    }

    $wdays = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $dayList = array_map(fn($d) => $d . ' (' . $wdays[(int) date('N', strtotime($d))] . ')', $days);

    $items = array_map(fn($o) => [
        'id' => (int) $o['occ_id'], 'titel' => $o['title'], 'typ' => $o['type'],
        'domain' => $o['domain'], 'minuten' => (int) $o['time_estimate'],
        'energie' => $o['energy'], 'prio' => (int) $o['priority'],
        'deadline' => $o['due_at'] ? substr($o['due_at'], 0, 10) : null,
    ], $pool);

    $system = 'Du bist der Wochen-Planer von Taskly (ADHS-App). Verteile die Aufgaben fair über die '
        . 'Woche. Antworte mit STRIKTEM JSON: {"assignments":[{"id":<id>,"date":"YYYY-MM-DD"}]} — jede '
        . 'Aufgabe genau einmal, date MUSS aus der Tagesliste stammen. Regeln: max 4 Aufgaben/Tag; '
        . 'höchstens 1 schwere (60+ Min oder Energie hoch) pro Tag; Domains über die Woche mischen; '
        . 'Deadlines am oder vor dem Fälligkeitstag; mindestens einen leichten Tag frei lassen; '
        . 'Überfälliges sanft nach vorne. Kein Schuld-Ton, keine Erklärungen.';

    $user = "Tage:\n" . implode("\n", $dayList) . "\n\nAufgaben:\n" . json_encode($items, JSON_UNESCAPED_UNICODE);

    $text = claude_call($CONFIG['anthropic']['model_select'], $system, $user, 1500);
    if ($text === null) {
        return $fallback;
    }
    $data = extract_json($text);
    if (!$data || empty($data['assignments'])) {
        return $fallback;
    }

    $valid = array_flip($days);
    $byId  = [];
    foreach ($pool as $o) {
        $byId[(int) $o['occ_id']] = true;
    }
    $assign = [];
    foreach ($data['assignments'] as $a) {
        $id = (int) ($a['id'] ?? 0);
        $d  = (string) ($a['date'] ?? '');
        if (isset($byId[$id]) && isset($valid[$d])) {
            $assign[$id] = $d;
        }
    }
    // Lücken (von der KI vergessen) deterministisch füllen
    foreach ($fallback as $id => $d) {
        if (!isset($assign[$id])) {
            $assign[$id] = $d;
        }
    }
    return $assign;
}

/** Plan anwenden: scheduled_date je Occurrence setzen. */
function apply_plan(array $assign): void
{
    if (!$assign) {
        return;
    }
    $up = db()->prepare('UPDATE task_occurrences SET scheduled_date = ? WHERE id = ?');
    foreach ($assign as $occId => $date) {
        $up->execute([$date, (int) $occId]);
    }
}

/** Die Woche neu verteilen → gibt den fertigen Plan zurück. */
function plan_week(int $userId, ?string $weekStart = null): array
{
    ensure_recurrences($userId, $weekStart);
    $days = week_days($weekStart ? new DateTimeImmutable($weekStart) : null);
    $pool = plannable_pool($userId, $weekStart);
    apply_plan(ai_distribute($pool, $days));
    return get_week_plan($userId, $weekStart);
}

/** Den aktuellen Wochenplan lesen, nach Tagen gruppiert. */
function get_week_plan(int $userId, ?string $weekStart = null): array
{
    $start = new DateTimeImmutable($weekStart ?? 'today');
    $days  = week_days($start);
    $end   = $start->modify('+6 day')->format('Y-m-d');
    $wdays = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    $st = db()->prepare(
        "SELECT o.id AS occ_id, o.scheduled_date, o.status,
                t.id AS task_id, t.title, t.type, t.domain, t.time_estimate, t.energy, t.base_xp, t.due_at, t.fixed_at,
                (t.recurrence_rule IS NOT NULL AND t.recurrence_rule <> '') AS recurring
           FROM task_occurrences o
           JOIN tasks t ON t.id = o.task_id
          WHERE (o.assignee_id = :uid OR o.assignee_id IS NULL)
            AND o.status IN ('open','done')
            AND o.scheduled_date BETWEEN :start AND :end
          ORDER BY o.scheduled_date, (t.type='termin') DESC, t.fixed_at, t.priority DESC"
    );
    $st->execute([':uid' => $userId, ':start' => $start->format('Y-m-d'), ':end' => $end]);

    $byDay = array_fill_keys($days, null);
    foreach ($days as $d) {
        $byDay[$d] = [];
    }
    foreach ($st->fetchAll() as $o) {
        $d = $o['scheduled_date'];
        if (!isset($byDay[$d])) {
            continue;
        }
        $byDay[$d][] = [
            'occ_id'        => (int) $o['occ_id'],
            'task_id'       => (int) $o['task_id'],
            'title'         => $o['title'],
            'type'          => $o['type'],
            'domain'        => $o['domain'],
            'time_estimate' => (int) $o['time_estimate'],
            'energy'        => $o['energy'],
            'base_xp'       => (int) $o['base_xp'],
            'fixed_at'      => $o['fixed_at'],
            'recurring'     => (bool) $o['recurring'],
            'done'          => $o['status'] === 'done',
        ];
    }

    $out = [];
    foreach ($days as $i => $d) {
        $out[] = [
            'date'    => $d,
            'weekday' => $wdays[(int) date('N', strtotime($d))],
            'is_today' => $i === 0,
            'tasks'   => $byDay[$d],
        ];
    }
    return $out;
}
