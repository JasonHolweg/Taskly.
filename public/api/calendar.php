<?php
require __DIR__ . '/../../src/bootstrap.php';

// --- Öffentlicher Feed (Token in URL, keine Session) ---
$token = (string) ($_GET['token'] ?? '');
if ($token !== '') {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        http_response_code(404);
        exit('Not found');
    }
    $st = db()->prepare('SELECT id FROM users WHERE cal_token = ?');
    $st->execute([$token]);
    $uid = (int) ($st->fetchColumn() ?: 0);
    if (!$uid) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="taskly.ics"');
    header('Cache-Control: max-age=3600');
    echo build_ics($uid);
    exit;
}

// --- Abo-Info für den eingeloggten User ---
$uid = require_auth();
$tok = ensure_cal_token($uid);
$host = 'taskly.jasonholweg.de';
json_out([
    'url'    => "https://$host/cal/$tok.ics",
    'webcal' => "webcal://$host/cal/$tok.ics",
]);
