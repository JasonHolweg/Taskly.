<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/_pick.php';
$uid = require_auth();

$in = body() + $_GET;

// Quick-Select-Kontext (Zeit 10/30/60 · Energie müde/ok/voll)
$time   = (int) ($in['time'] ?? 30);
$energy = (string) ($in['energy'] ?? 'ok');
$ctxLoc = (string) ($in['context'] ?? 'egal');

if (!in_array($time, [10, 30, 60], true)) {
    $time = 30;
}
if (!in_array($energy, ['müde', 'mude', 'ok', 'voll'], true)) {
    $energy = 'ok';
}

$_SESSION['ctx'] = ['time' => $time, 'energy' => $energy, 'context' => $ctxLoc];

// Neue Abfrage = frische Session-Skip-Liste, außer explizit fortgesetzt (keep=1).
if (empty($in['keep'])) {
    $_SESSION['skips']  = [];
    $_SESSION['recent'] = [];
}

json_out(pick_response($uid));
