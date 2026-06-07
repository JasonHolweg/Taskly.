<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    json_out(friends_overview($uid));
}

$b      = body();
$action = (string) ($b['action'] ?? '');

switch ($action) {
    case 'add':
        $res = add_friend_by_code($uid, (string) ($b['code'] ?? ''));
        break;
    case 'accept':
        $res = accept_friend($uid, (int) ($b['user_id'] ?? 0));
        break;
    case 'remove':
    case 'decline':
        $res = remove_friend($uid, (int) ($b['user_id'] ?? 0));
        break;
    default:
        fail('Unbekannte Aktion.');
}

if (isset($res['error'])) {
    fail($res['error'], 409);
}
$res['overview'] = friends_overview($uid);
json_out($res);
