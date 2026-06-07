<?php
/**
 * Geteilte „Was jetzt?"-Antwort. Wird von whatnow/skip/snooze nach dem
 * Bootstrap eingebunden. Liest Kontext + Skip-/Recent-Liste aus der Session.
 */
declare(strict_types=1);

function pick_response(int $uid): array
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
        'reason' => reason_for($pick, $ctx),
    ];
}
