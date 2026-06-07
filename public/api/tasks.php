<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();

$st = db()->prepare('SELECT household_id FROM users WHERE id = ?');
$st->execute([$uid]);
$householdId = (int) $st->fetchColumn();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Aktive Tasks des Haushalts + Status der jüngsten Occurrence (kein Schuld-UI: nur Inspektion/Edit)
    $st = db()->prepare(
        "SELECT t.id, t.title, t.notes, t.type, t.domain, t.time_estimate, t.energy,
                t.context, t.priority, t.base_xp, t.due_at, t.fixed_at, t.recurrence_rule
           FROM tasks t
          WHERE t.household_id = ? AND t.active = 1
          ORDER BY t.created_at DESC"
    );
    $st->execute([$householdId]);
    json_out(['tasks' => $st->fetchAll()]);
}

if ($method === 'POST' || $method === 'PUT') {
    $b  = body();
    $id = (int) ($b['id'] ?? 0);
    if ($id <= 0) {
        fail('Task-id fehlt.');
    }

    // Nur Felder anfassen, die mitgeschickt wurden (Whitelist).
    $fields = [];
    $params = [];
    $allowed = [
        'title' => 's', 'notes' => 's', 'time_estimate' => 'i', 'priority' => 'i',
        'energy' => 's', 'context' => 's', 'domain' => 's', 'active' => 'i',
    ];
    foreach ($allowed as $f => $type) {
        if (array_key_exists($f, $b)) {
            $fields[] = "$f = ?";
            $params[] = $type === 'i' ? (int) $b[$f] : (string) $b[$f];
        }
    }
    if (!$fields) {
        fail('Nichts zu ändern.');
    }

    $params[] = $id;
    $params[] = $householdId;
    $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND household_id = ?';
    db()->prepare($sql)->execute($params);

    json_out(['ok' => true]);
}

fail('Methode nicht erlaubt.', 405);
