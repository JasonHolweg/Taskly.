<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/_pick.php';
require_method('POST');
$uid = require_auth();

$occId = (int) (body()['occ_id'] ?? 0);
if ($occId <= 0) {
    fail('occ_id fehlt.');
}

// Skip ist straffrei (rules.md §5.3) — nur für diese Session merken.
$skips = array_map('intval', $_SESSION['skips'] ?? []);
if (!in_array($occId, $skips, true)) {
    $skips[] = $occId;
}
$_SESSION['skips'] = $skips;

// Direkt die nächstbeste vorschlagen.
json_out(pick_response($uid));
