<?php
/**
 * Taskly — Helpers: JSON-I/O, Auth, Copy-Rotation.
 */
declare(strict_types=1);

/** JSON-Antwort senden und beenden. */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Fehler-Antwort. */
function fail(string $msg, int $status = 400): void
{
    json_out(['error' => $msg], $status);
}

/** Request-Body als assoziatives Array (JSON oder form). */
function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return $j;
        }
    }
    return $_POST ?: [];
}

/** Methode erzwingen. */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        fail('Methode nicht erlaubt.', 405);
    }
}

/** Eingeloggte user_id oder null. */
function current_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
}

/** Auth erzwingen, gibt user_id zurück. */
function require_auth(): int
{
    $uid = current_user_id();
    if ($uid === null) {
        fail('Nicht eingeloggt.', 401);
    }
    return $uid;
}

/**
 * Copy-Rotation (rules.md §7). Wählt zufällig eine Variante und füllt {platzhalter}.
 * Variantenpool bewusst klein, aber rotiert (gegen Abnutzung).
 */
function copy_line(string $key, array $vars = []): string
{
    static $pool = [
        'whatnow_tired' => [
            'Wenig Akku? Dann was Leichtes: {task}.',
            'Müde? Nimm die kleine Sache: {task}, nur {min} Min.',
        ],
        'whatnow_full' => [
            'Du hast Energie — pack das Dickere an: {task}.',
            'Voller Akku? Guter Moment für {task}.',
        ],
        'whatnow_ok' => [
            'Wie wär’s mit {task}? Dauert {min} Min.',
            'Mach kurz {task} — gibt {xp} XP.',
        ],
        'whatnow_deadline' => [
            '{task} wird langsam dringend — guter Moment.',
            '{task} hat bald Frist. Jetzt kurz ran?',
        ],
        'done' => [
            'Erledigt. +{xp} XP. Eine weniger im Kopf. ✨',
            'Stark. Der Tanuki freut sich. +{xp} XP.',
        ],
        'levelup' => [
            'Level {n}! 🎉 Ein Glücksumschlag wartet auf dich.',
            'Level {n} erreicht! Der Tanuki klatscht. 🎉',
        ],
        'envelope' => [
            'Aufgemacht: +{sparks} Sparks. Zeit für ein neues Outfit?',
            'Glücksumschlag: +{sparks} Sparks. ✨',
        ],
        'streak_break' => [
            'Streak ist weg — passiert, war eine starke Serie. Heute eine Sache, und wir starten neu.',
            'Kein Drama. Dein Rekord steht bei {longest} Tagen. Auf geht’s, Schritt eins.',
        ],
        'empty' => [
            'Nichts dringend. Genieß die Pause. 🍵',
            'Für {time} Min & {energy} hab ich grad nichts Passendes — Pause ist auch ok.',
        ],
    ];

    $variants = $pool[$key] ?? ['{task}'];
    $line = $variants[random_int(0, count($variants) - 1)];
    foreach ($vars as $k => $v) {
        $line = str_replace('{' . $k . '}', (string) $v, $line);
    }
    return $line;
}
