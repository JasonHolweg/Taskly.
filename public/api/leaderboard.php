<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();

json_out(['rows' => leaderboard($uid)]);
