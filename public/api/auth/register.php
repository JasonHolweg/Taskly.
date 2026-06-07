<?php
require __DIR__ . '/../../../src/bootstrap.php';
require_method('POST');

$b        = body();
$name     = trim((string) ($b['name'] ?? ''));
$email    = strtolower(trim((string) ($b['email'] ?? '')));
$password = (string) ($b['password'] ?? '');
$invite   = strtoupper(trim((string) ($b['invite_code'] ?? '')));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    fail('Name, gültige E-Mail und Passwort (≥6 Zeichen) nötig.');
}

$pdo = db();

// E-Mail schon vergeben?
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) {
    fail('Diese E-Mail ist schon registriert.', 409);
}

// Haushalt finden (per Invite) oder neu anlegen
if ($invite !== '') {
    $st = $pdo->prepare('SELECT id FROM households WHERE invite_code = ?');
    $st->execute([$invite]);
    $hh = $st->fetch();
    if (!$hh) {
        fail('Einladungscode unbekannt.', 404);
    }
    $householdId = (int) $hh['id'];
} else {
    $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
    $pdo->prepare('INSERT INTO households (name, invite_code) VALUES (?, ?)')
        ->execute([$name . 's Haushalt', $code]);
    $householdId = (int) $pdo->lastInsertId();
}

$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$hash = password_hash($password, $algo);

$pdo->prepare('INSERT INTO users (household_id, name, email, password_hash) VALUES (?, ?, ?, ?)')
    ->execute([$householdId, $name, $email, $hash]);
$userId = (int) $pdo->lastInsertId();

// Gamification-Anker
$pdo->prepare('INSERT INTO user_progress (user_id) VALUES (?)')->execute([$userId]);
$pdo->prepare('INSERT INTO tanuki_profile (user_id) VALUES (?)')->execute([$userId]);
ensure_friend_code($userId);

$_SESSION['uid'] = $userId;

json_out([
    'ok'      => true,
    'user'    => ['id' => $userId, 'name' => $name, 'household_id' => $householdId],
]);
