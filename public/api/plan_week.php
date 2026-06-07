<?php
require __DIR__ . '/../../src/bootstrap.php';
require_method('POST');
$uid = require_auth();

// Woche neu verteilen (Haiku, mit PHP-Fallback) und den fertigen Plan zurückgeben.
$days = plan_week($uid);
json_out(['ok' => true, 'days' => $days, 'ai_used' => claude_available()]);
