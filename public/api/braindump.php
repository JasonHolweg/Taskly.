<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

$st = db()->prepare('SELECT household_id FROM users WHERE id = ?');
$st->execute([$uid]);
$householdId = (int) $st->fetchColumn();

$text = trim((string) (body()['text'] ?? ''));
if ($text === '') {
    fail('Kein Text zum Erfassen.');
}

$tasks = parse_braindump($text);
if (!$tasks) {
    fail('Konnte daraus keine Aufgabe ableiten.');
}

$pdo = db();
$insTask = $pdo->prepare(
    'INSERT INTO tasks
       (household_id, owner_id, title, notes, type, domain, time_estimate, energy, context,
        priority, base_xp, recurrence_rule, due_at, fixed_at, created_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
// Eine erste Occurrence direkt anlegen (flexible/deadline landen im „Was jetzt?"-Pool).
$insOcc = $pdo->prepare(
    "INSERT INTO task_occurrences (task_id, assignee_id, scheduled_date, status)
     VALUES (?, ?, CURDATE(), 'open')"
);

$created = [];
$pdo->beginTransaction();
foreach ($tasks as $t) {
    $insTask->execute([
        $householdId, $uid, $t['title'], $t['notes'], $t['type'], $t['domain'],
        $t['time_estimate'], $t['energy'], $t['context'], $t['priority'],
        $t['base_xp'], $t['recurrence_rule'], $t['due_at'], $t['fixed_at'], $uid,
    ]);
    $taskId = (int) $pdo->lastInsertId();
    $insOcc->execute([$taskId, $uid]);
    $t['id'] = $taskId;
    $created[] = $t;
}
$pdo->commit();

// Skip-Liste der Session zurücksetzen — neuer Stoff im Pool.
$_SESSION['skips']  = [];
$_SESSION['recent'] = [];

json_out([
    'ok'      => true,
    'count'   => count($created),
    'tasks'   => $created,
    'ai_used' => claude_available(),
]);
