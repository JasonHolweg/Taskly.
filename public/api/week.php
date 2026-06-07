<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();

// Wiederkehrendes sicherstellen, dann den aktuellen Plan liefern (ohne neu zu verteilen).
ensure_recurrences($uid);
json_out(['days' => get_week_plan($uid)]);
