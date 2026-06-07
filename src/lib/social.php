<?php
/**
 * Taskly — Freunde, Leaderboard & Streak-Rettung (rules.md §4).
 * Ersetzt das Haushalts-Leaderboard durch ein freundschaftsbasiertes.
 */
declare(strict_types=1);

/** Freundes-Code des Users sicherstellen (generiert bei Bedarf). */
function ensure_friend_code(int $userId): string
{
    $pdo = db();
    $st = $pdo->prepare('SELECT friend_code FROM users WHERE id = ?');
    $st->execute([$userId]);
    $code = $st->fetchColumn();
    if ($code) {
        return (string) $code;
    }
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE friend_code = ?');
        $chk->execute([$code]);
    } while ($chk->fetchColumn());
    $pdo->prepare('UPDATE users SET friend_code = ? WHERE id = ?')->execute([$code, $userId]);
    return $code;
}

/** Akzeptierte Freundes-IDs eines Users. */
function friend_ids(int $userId): array
{
    $st = db()->prepare(
        "SELECT IF(user_id = ?, friend_id, user_id) AS fid
           FROM friendships
          WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'"
    );
    $st->execute([$userId, $userId, $userId]);
    return array_map('intval', array_column($st->fetchAll(), 'fid'));
}

/** Eine Person fürs Leaderboard aufbereiten. */
function leaderboard_row(int $userId, bool $isMe): array
{
    $st = db()->prepare(
        'SELECT u.id, u.name, p.xp_total, p.sparks, p.streak_count, p.streak_state, p.longest_streak
           FROM users u JOIN user_progress p ON p.user_id = u.id WHERE u.id = ?'
    );
    $st->execute([$userId]);
    $r = $st->fetch();
    if (!$r) {
        return [];
    }
    $lvl = level_from_xp((int) $r['xp_total']);
    return [
        'user_id' => (int) $r['id'],
        'name'    => $r['name'],
        'level'   => $lvl['level'],
        'xp'      => (int) $r['xp_total'],
        'streak'  => (int) $r['streak_count'],
        'frozen'  => $r['streak_state'] === 'frozen',
        'is_me'   => $isMe,
    ];
}

/** Leaderboard: ich + akzeptierte Freunde, nach XP sortiert. */
function leaderboard(int $userId): array
{
    // Freunde vor dem Anzeigen abgleichen (Eis/Bruch aktuell halten)
    reconcile_streak($userId);
    $rows = [leaderboard_row($userId, true)];
    foreach (friend_ids($userId) as $fid) {
        reconcile_streak($fid);
        $row = leaderboard_row($fid, false);
        if ($row) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => $b['xp'] <=> $a['xp']);
    foreach ($rows as $i => &$r) {
        $r['rank'] = $i + 1;
    }
    return $rows;
}

/** Freunde-Übersicht: Code, akzeptierte Freunde, offene Anfragen. */
function friends_overview(int $userId): array
{
    $pdo = db();

    $in = $pdo->prepare(
        "SELECT u.id, u.name FROM friendships f JOIN users u ON u.id = f.user_id
          WHERE f.friend_id = ? AND f.status = 'pending'"
    );
    $in->execute([$userId]);

    $out = $pdo->prepare(
        "SELECT u.id, u.name FROM friendships f JOIN users u ON u.id = f.friend_id
          WHERE f.user_id = ? AND f.status = 'pending'"
    );
    $out->execute([$userId]);

    $friends = [];
    foreach (friend_ids($userId) as $fid) {
        $row = leaderboard_row($fid, false);
        if ($row) {
            $friends[] = $row;
        }
    }

    return [
        'code'     => ensure_friend_code($userId),
        'friends'  => $friends,
        'incoming' => array_map(fn($r) => ['user_id' => (int) $r['id'], 'name' => $r['name']], $in->fetchAll()),
        'outgoing' => array_map(fn($r) => ['user_id' => (int) $r['id'], 'name' => $r['name']], $out->fetchAll()),
    ];
}

/** Freundschaftsanfrage per Code senden (oder bestehende Gegenanfrage annehmen). */
function add_friend_by_code(int $userId, string $code): array
{
    $pdo = db();
    $code = strtoupper(trim($code));
    $st = $pdo->prepare('SELECT id FROM users WHERE friend_code = ?');
    $st->execute([$code]);
    $target = (int) ($st->fetchColumn() ?: 0);

    if (!$target) {
        return ['error' => 'Code unbekannt.'];
    }
    if ($target === $userId) {
        return ['error' => 'Das ist dein eigener Code. 😉'];
    }

    // Schon befreundet?
    if (in_array($target, friend_ids($userId), true)) {
        return ['error' => 'Ihr seid schon Freunde.'];
    }

    // Gibt es eine Gegenanfrage (target -> me)? Dann direkt annehmen.
    $rev = $pdo->prepare("SELECT id FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    $rev->execute([$target, $userId]);
    if ($rev->fetchColumn()) {
        $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id = ? AND friend_id = ?")
            ->execute([$target, $userId]);
        return ['ok' => true, 'accepted' => true];
    }

    // Bereits eine offene eigene Anfrage?
    $exists = $pdo->prepare('SELECT id FROM friendships WHERE user_id = ? AND friend_id = ?');
    $exists->execute([$userId, $target]);
    if ($exists->fetchColumn()) {
        return ['error' => 'Anfrage läuft bereits.'];
    }

    $pdo->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')")
        ->execute([$userId, $target]);
    return ['ok' => true, 'requested' => true];
}

/** Eingehende Anfrage annehmen. */
function accept_friend(int $userId, int $fromUser): array
{
    $st = db()->prepare("UPDATE friendships SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    $st->execute([$fromUser, $userId]);
    return ['ok' => $st->rowCount() > 0];
}

/** Anfrage ablehnen oder Freund entfernen (beide Richtungen). */
function remove_friend(int $userId, int $other): array
{
    db()->prepare(
        'DELETE FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)'
    )->execute([$userId, $other, $other, $userId]);
    return ['ok' => true];
}

/** Streak-Rettung: einen eingefrorenen Freund anstupsen. */
function send_rescue(int $userId, int $toUser): array
{
    if (!in_array($toUser, friend_ids($userId), true)) {
        return ['error' => 'Das ist kein Freund.'];
    }
    reconcile_streak($toUser);
    $st = db()->prepare("SELECT streak_state FROM user_progress WHERE user_id = ?");
    $st->execute([$toUser]);
    if ($st->fetchColumn() !== 'frozen') {
        return ['error' => 'Diese Streak ist gar nicht auf Eis.'];
    }
    db()->prepare('INSERT INTO rescues (to_user, from_user) VALUES (?, ?)')->execute([$toUser, $userId]);
    return ['ok' => true];
}

/** Ungesehene Rettungs-Anstupser für den User (für das Heute-Banner). */
function pending_rescues(int $userId): array
{
    $st = db()->prepare(
        'SELECT u.name FROM rescues r JOIN users u ON u.id = r.from_user
          WHERE r.to_user = ? AND r.seen = 0
          GROUP BY u.id, u.name ORDER BY MAX(r.created_at) DESC'
    );
    $st->execute([$userId]);
    return array_column($st->fetchAll(), 'name');
}

/** Rettungs-Anstupser als gesehen markieren (nach erfolgreicher Rettung/Anzeige). */
function clear_rescues(int $userId): void
{
    db()->prepare('UPDATE rescues SET seen = 1 WHERE to_user = ? AND seen = 0')->execute([$userId]);
}
