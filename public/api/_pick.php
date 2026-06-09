<?php
/**
 * Geteilte „Was jetzt?"-Antwort. Wird von whatnow/skip/snooze nach dem
 * Bootstrap eingebunden. Liest Kontext + Skip-/Recent-Liste aus der Session.
 */
declare(strict_types=1);

function pick_response(int $uid, bool $withReason = true): array
{
    $st = db()->prepare('SELECT household_id FROM users WHERE id = ?');
    $st->execute([$uid]);
    $householdId = (int) $st->fetchColumn();

    $ctx = $_SESSION['ctx'] ?? ['time' => 30, 'energy' => 'ok', 'context' => 'egal'];
    $skips  = array_map('intval', $_SESSION['skips'] ?? []);
    $recent = array_map('intval', $_SESSION['recent'] ?? []);

    $pool   = fetch_pool($uid, $householdId);
    $ranked = rank_pool($pool, $ctx, $skips, $recent);

    if (!$ranked) {
        // Ehrlicher Leerzustand — kein erzwungener Vorschlag (rules.md §5.3)
        return [
            'pick'    => null,
            'message' => copy_line('empty', ['time' => $ctx['time'], 'energy' => $ctx['energy']]),
        ];
    }

    $pick = $ranked[0];

    // Pick als „kürzlich vorgeschlagen" merken (Anti-Repetition), Liste kurz halten.
    array_unshift($recent, (int) $pick['occ_id']);
    $_SESSION['recent'] = array_slice(array_unique($recent), 0, 6);

    return [
        'pick' => [
            'occ_id'        => (int) $pick['occ_id'],
            'task_id'       => (int) $pick['task_id'],
            'title'         => $pick['title'],
            'type'          => $pick['type'],
            'domain'        => $pick['domain'],
            'time_estimate' => (int) $pick['time_estimate'],
            'energy'        => $pick['energy'],
            'base_xp'       => (int) $pick['base_xp'],
            'due_at'        => $pick['due_at'],
        ],
        // Begründung optional: das Frontend holt sie via reason.php nach (Tipp-Animation),
        // damit die Aufgabe sofort erscheint statt auf Haiku zu warten.
        'reason' => $withReason ? smart_reason($pick, $ctx) : null,
    ];
}

/**
 * Baut die für smart_reason() nötigen Felder einer Occurrence aus der DB nach
 * (auth-gescoped übers Household). Wird von reason.php genutzt.
 */
function pick_for_reason(int $uid, int $occId): ?array
{
    $st = db()->prepare(
        "SELECT t.title, t.type, t.domain, t.time_estimate, t.energy, t.base_xp, t.due_at
           FROM task_occurrences o
           JOIN tasks t ON t.id = o.task_id
           JOIN users u ON u.household_id = t.household_id
          WHERE o.id = :occ AND u.id = :uid
          LIMIT 1"
    );
    $st->execute([':occ' => $occId, ':uid' => $uid]);
    $row = $st->fetch();
    return $row ?: null;
}
