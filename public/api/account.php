<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $st = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
    $st->execute([$uid]);
    json_out($st->fetch() ?: []);
}

$b      = body();
$action = (string) ($b['action'] ?? '');

if ($action === 'profile') {
    $name  = trim((string) ($b['name'] ?? ''));
    $email = strtolower(trim((string) ($b['email'] ?? '')));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Name und gültige E-Mail nötig.');
    }
    // E-Mail schon von jemand anderem belegt?
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
    $chk->execute([$email, $uid]);
    if ($chk->fetchColumn()) {
        fail('Diese E-Mail wird schon verwendet.', 409);
    }
    $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $uid]);
    json_out(['ok' => true, 'name' => $name, 'email' => $email]);
}

if ($action === 'password') {
    $cur = (string) ($b['current'] ?? '');
    $new = (string) ($b['new'] ?? '');
    if (strlen($new) < 6) {
        fail('Neues Passwort braucht ≥6 Zeichen.');
    }
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$uid]);
    if (!password_verify($cur, (string) $st->fetchColumn())) {
        fail('Aktuelles Passwort stimmt nicht.', 403);
    }
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, $algo), $uid]);
    json_out(['ok' => true]);
}

fail('Unbekannte Aktion.');
