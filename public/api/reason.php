<?php
/**
 * Smarte „Warum jetzt?"-Begründung für einen bereits angezeigten Vorschlag.
 * Wird vom Frontend NACH dem Pick nachgeladen (Tipp-Animation), damit die
 * Aufgabe sofort erscheint und nicht auf Haiku wartet.
 */
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/_pick.php';
require_method('POST');
$uid = require_auth();

$occId = (int) (body()['occ_id'] ?? 0);
if ($occId <= 0) {
    fail('occ_id fehlt.');
}

$pick = pick_for_reason($uid, $occId);
if ($pick === null) {
    fail('Aufgabe nicht gefunden.', 404);
}

$ctx = $_SESSION['ctx'] ?? ['time' => 30, 'energy' => 'ok', 'context' => 'egal'];

json_out(['reason' => smart_reason($pick, $ctx)]);
