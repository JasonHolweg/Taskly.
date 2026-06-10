<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();
journey_gate($uid);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $b      = body();
    $action = (string) ($b['action'] ?? '');

    switch ($action) {
        case 'start':
            $destination = trim((string) ($b['destination'] ?? ''));
            if ($destination === '') {
                fail('destination fehlt.');
            }
            journey_start($uid, $destination);
            break;

        case 'equip':
        case 'unequip':
            $itemId = (int) ($b['item_id'] ?? 0);
            if ($itemId <= 0) {
                fail('item_id fehlt.');
            }
            journey_equip($uid, $itemId, $action === 'equip');
            break;

        default:
            fail('Unbekannte Aktion.');
    }

    json_out(['ok' => true, 'state' => journey_state($uid)]);
}

// GET: erst passive Bewegung abrechnen, dann den frischen Stand liefern.
$tick = journey_tick($uid);
$state = journey_state($uid);
if (!empty($tick['events'])) {
    $state['new_events'] = $tick['events'];
}
json_out($state);
