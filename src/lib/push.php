<?php
/**
 * Taskly — Web-Push (architecture.md §5.1). VAPID + verschlüsselte Payloads
 * via minishlink/web-push. Ohne vendor/ oder VAPID-Keys still deaktiviert.
 */
declare(strict_types=1);

function push_available(): bool
{
    global $CONFIG;
    return trim((string) ($CONFIG['vapid']['public_key'] ?? '')) !== ''
        && trim((string) ($CONFIG['vapid']['private_key'] ?? '')) !== ''
        && is_file(__DIR__ . '/../../vendor/autoload.php');
}

/** WebPush-Client (Singleton) oder null, wenn nicht verfügbar. */
function web_push()
{
    global $CONFIG;
    static $wp = null; static $tried = false;
    if ($tried) {
        return $wp;
    }
    $tried = true;
    if (!push_available()) {
        return null;
    }
    require_once __DIR__ . '/../../vendor/autoload.php';
    $wp = new \Minishlink\WebPush\WebPush(['VAPID' => [
        'subject'    => $CONFIG['vapid']['subject'],
        'publicKey'  => $CONFIG['vapid']['public_key'],
        'privateKey' => $CONFIG['vapid']['private_key'],
    ]]);
    $wp->setDefaultOptions(['TTL' => 3600, 'urgency' => 'normal']);
    return $wp;
}

/**
 * Push an einen User senden. Räumt abgelaufene Subscriptions auf.
 * Gibt true bei erfolgreichem Versand zurück.
 */
function send_push(int $userId, string $title, string $body, string $url = '/'): bool
{
    $wp = web_push();
    if (!$wp) {
        return false;
    }
    $st = db()->prepare('SELECT push_sub FROM users WHERE id = ?');
    $st->execute([$userId]);
    $raw = $st->fetchColumn();
    if (!$raw) {
        return false;
    }
    $sub = json_decode((string) $raw, true);
    if (!$sub || empty($sub['endpoint'])) {
        return false;
    }

    try {
        $subscription = \Minishlink\WebPush\Subscription::create($sub);
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);
        $report  = $wp->sendOneNotification($subscription, $payload);
    } catch (\Throwable $e) {
        return false;
    }

    if (!$report->isSuccess()) {
        $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
        if (in_array($code, [404, 410], true)) {
            db()->prepare('UPDATE users SET push_sub = NULL WHERE id = ?')->execute([$userId]);
        }
        return false;
    }
    return true;
}

/** Nudge protokollieren (Dedup + Audit, Tabelle nudges). */
function log_nudge(int $userId, ?int $occId, string $template, string $status = 'sent'): void
{
    db()->prepare(
        "INSERT INTO nudges (user_id, occurrence_id, channel, template, status, sent_at)
         VALUES (?, ?, 'push', ?, ?, NOW())"
    )->execute([$userId, $occId, $template, $status]);
}
