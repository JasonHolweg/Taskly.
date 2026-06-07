<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

$res = send_rescue($uid, (int) (body()['user_id'] ?? 0));
if (isset($res['error'])) {
    fail($res['error'], 409);
}
json_out($res);
