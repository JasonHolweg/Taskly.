<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_auth();
global $CONFIG;

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $st = $pdo->prepare('SELECT push_sub, nudge_prefs FROM users WHERE id = ?');
    $st->execute([$uid]);
    $r = $st->fetch();
    $prefs = $r['nudge_prefs'] ? json_decode($r['nudge_prefs'], true) : null;
    json_out([
        'public_key' => $CONFIG['vapid']['public_key'] ?? '',
        'available'  => push_available(),
        'subscribed' => !empty($r['push_sub']),
        'prefs'      => $prefs ?: ['enabled' => true, 'windows' => ['09:00', '12:00', '18:00']],
    ]);
}

$b      = body();
$action = (string) ($b['action'] ?? '');

switch ($action) {
    case 'subscribe':
        $sub = $b['subscription'] ?? null;
        if (!is_array($sub) || empty($sub['endpoint'])) {
            fail('Ungültige Subscription.');
        }
        $pdo->prepare('UPDATE users SET push_sub = ? WHERE id = ?')
            ->execute([json_encode($sub, JSON_UNESCAPED_SLASHES), $uid]);
        json_out(['ok' => true]);
        // no break

    case 'unsubscribe':
        $pdo->prepare('UPDATE users SET push_sub = NULL WHERE id = ?')->execute([$uid]);
        json_out(['ok' => true]);
        // no break

    case 'prefs':
        $windows = array_values(array_filter(
            (array) ($b['windows'] ?? []),
            fn($w) => (bool) preg_match('/^\d{2}:\d{2}$/', (string) $w)
        ));
        $prefs = ['enabled' => (bool) ($b['enabled'] ?? true), 'windows' => $windows];
        $pdo->prepare('UPDATE users SET nudge_prefs = ? WHERE id = ?')
            ->execute([json_encode($prefs, JSON_UNESCAPED_SLASHES), $uid]);
        json_out(['ok' => true, 'prefs' => $prefs]);
        // no break

    case 'test':
        $ok = send_push($uid, 'Taskly 🦝', 'Push läuft! Hier kommen deine Nudges an.', '/');
        json_out(['ok' => $ok]);
        // no break

    default:
        fail('Unbekannte Aktion.');
}
