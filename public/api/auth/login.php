<?php
require __DIR__ . '/../../../src/bootstrap.php';
require_method('POST');

$b        = body();
$email    = strtolower(trim((string) ($b['email'] ?? '')));
$password = (string) ($b['password'] ?? '');

if ($email === '' || $password === '') {
    fail('E-Mail und Passwort nötig.');
}

$st = db()->prepare('SELECT id, name, household_id, password_hash FROM users WHERE email = ?');
$st->execute([$email]);
$u = $st->fetch();

if (!$u || !password_verify($password, (string) $u['password_hash'])) {
    fail('E-Mail oder Passwort falsch.', 401);
}

$_SESSION['uid'] = (int) $u['id'];

json_out([
    'ok'   => true,
    'user' => ['id' => (int) $u['id'], 'name' => $u['name'], 'household_id' => (int) $u['household_id']],
]);
