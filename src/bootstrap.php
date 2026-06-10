<?php
/**
 * Taskly — zentraler Bootstrap.
 * Von jedem API-Endpoint zuerst eingebunden.
 */
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'config.php fehlt — config.example.php kopieren und ausfüllen.']);
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configPath;

if (!empty($CONFIG['app']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Europe/Berlin');

require __DIR__ . '/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/gamification.php';
require __DIR__ . '/lib/selection.php';
require __DIR__ . '/lib/cosmetics.php';
require __DIR__ . '/lib/gacha.php';
require __DIR__ . '/lib/claude.php';
require __DIR__ . '/lib/recurrence.php';
require __DIR__ . '/lib/planner.php';
require __DIR__ . '/lib/social.php';
require __DIR__ . '/lib/push.php';
require __DIR__ . '/lib/calendar.php';
require __DIR__ . '/lib/journey.php';

// Session (für simple Familien-Auth, architecture.md §6) — im CLI/Cron nicht nötig.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('taskly_sess');
    session_start();
}
