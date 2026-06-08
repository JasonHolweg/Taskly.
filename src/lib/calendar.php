<?php
/**
 * Taskly — Kalender-Export als iCal-Feed (architecture.md §13).
 * Termine = getimete Events (+ Alarm/RRULE); geplante/wiederkehrende Aufgaben
 * = Ganztages-Events; Deadlines = „Frist"-Marker. Read-only, Token-geschützt.
 */
declare(strict_types=1);

const CAL_DOMAIN = 'taskly.jasonholweg.de';

/** Kalender-Token sicherstellen (generiert bei Bedarf). */
function ensure_cal_token(int $userId): string
{
    $pdo = db();
    $st = $pdo->prepare('SELECT cal_token FROM users WHERE id = ?');
    $st->execute([$userId]);
    $tok = $st->fetchColumn();
    if ($tok) {
        return (string) $tok;
    }
    do {
        $tok = bin2hex(random_bytes(16)); // 32 hex
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE cal_token = ?');
        $chk->execute([$tok]);
    } while ($chk->fetchColumn());
    $pdo->prepare('UPDATE users SET cal_token = ? WHERE id = ?')->execute([$tok, $userId]);
    return $tok;
}

function ics_escape(string $s): string
{
    return str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], trim($s));
}

/** Zeile nach RFC 5545 auf 75 Oktette falten. */
function ics_fold(string $line): string
{
    $out = ''; $len = 0; $buf = '';
    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $b = strlen($ch);
        if ($len + $b > 73) { $out .= $buf . "\r\n "; $buf = ''; $len = 1; }
        $buf .= $ch; $len += $b;
    }
    return $out . $buf;
}

/** Berliner Wandzeit-String → UTC-Stempel (YYYYMMDDTHHMMSSZ). */
function ics_utc(string $berlin): string
{
    $dt = new DateTime($berlin, new DateTimeZone('Europe/Berlin'));
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}

/** RRULE aus DB normalisieren (Präfix weg) → iCal-RRULE-Zeile-Wert. */
function ics_rrule(string $rule): string
{
    $rule = strtoupper(trim($rule));
    if (strncmp($rule, 'RRULE:', 6) === 0) {
        $rule = substr($rule, 6);
    }
    return $rule;
}

/** Kompletten iCal-Feed eines Users bauen. */
function build_ics(int $userId): string
{
    $pdo  = db();
    $now  = ics_utc(date('Y-m-d H:i:s'));
    $from = (new DateTimeImmutable('today'))->modify('-7 day');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Taskly//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:Taskly',
        'X-WR-TIMEZONE:Europe/Berlin',
        'REFRESH-INTERVAL;VALUE=DURATION:PT2H',
        'X-PUBLISHED-TTL:PT2H',
    ];

    $ev = function (array $p) use (&$lines, $now) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $p['uid'];
        $lines[] = 'DTSTAMP:' . $now;
        foreach ($p['body'] as $l) {
            $lines[] = $l;
        }
        $lines[] = 'SUMMARY:' . ics_escape($p['summary']);
        $lines[] = 'END:VEVENT';
    };

    // ---- Tasks ----
    $st = $pdo->prepare('SELECT * FROM tasks WHERE created_by = ? AND active = 1');
    $st->execute([$userId]);
    foreach ($st->fetchAll() as $t) {
        $id   = (int) $t['id'];
        $rec  = !empty($t['recurrence_rule']) ? ics_rrule($t['recurrence_rule']) : null;

        if ($t['type'] === 'termin' && !empty($t['fixed_at'])) {
            $hasTime = substr($t['fixed_at'], 11, 8) !== '00:00:00';
            if ($hasTime) {
                $dur  = max(5, (int) ($t['time_estimate'] ?: 30));
                $body = [
                    'DTSTART:' . ics_utc($t['fixed_at']),
                    'DURATION:PT' . $dur . 'M',
                    'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . ics_escape($t['title']),
                    'TRIGGER:-PT30M', 'END:VALARM',
                ];
            } else {
                // Termin ohne echte Uhrzeit → Ganztags
                $body = ['DTSTART;VALUE=DATE:' . date('Ymd', strtotime($t['fixed_at']))];
            }
            if ($rec) { $body[] = 'RRULE:' . $rec; }
            $ev(['uid' => "task-$id@" . CAL_DOMAIN, 'summary' => $t['title'], 'body' => $body]);
            continue;
        }

        // Wiederkehrende flexible/deadline → Ganztags-RRULE ab erstem Vorkommen
        if ($rec) {
            $anchor = $t['created_at'] ? new DateTimeImmutable($t['created_at']) : $from;
            $dates  = expand_rrule($t['recurrence_rule'], $from, $from->modify('+60 day'), $anchor);
            if ($dates) {
                $start = str_replace('-', '', $dates[0]);
                $ev([
                    'uid' => "task-$id@" . CAL_DOMAIN, 'summary' => $t['title'],
                    'body' => ['DTSTART;VALUE=DATE:' . $start, 'RRULE:' . $rec],
                ]);
            }
        }

        // Deadline → „Frist"-Marker am Fälligkeitstag
        if ($t['type'] === 'deadline' && !empty($t['due_at'])) {
            $day = date('Ymd', strtotime($t['due_at']));
            $ev([
                'uid' => "due-$id@" . CAL_DOMAIN, 'summary' => '⏰ Frist: ' . $t['title'],
                'body' => ['DTSTART;VALUE=DATE:' . $day],
            ]);
        }
    }

    // ---- Geplante einmalige flexible/deadline-Vorkommen (Ganztags) ----
    $occ = $pdo->prepare(
        "SELECT o.id AS occ_id, o.scheduled_date, t.title
           FROM task_occurrences o
           JOIN tasks t ON t.id = o.task_id
          WHERE t.created_by = ? AND t.active = 1
            AND t.type IN ('flexible','deadline')
            AND (t.recurrence_rule IS NULL OR t.recurrence_rule = '')
            AND o.status = 'open'
            AND o.scheduled_date IS NOT NULL
            AND o.scheduled_date >= ?"
    );
    $occ->execute([$userId, $from->format('Y-m-d')]);
    foreach ($occ->fetchAll() as $o) {
        $ev([
            'uid' => 'occ-' . (int) $o['occ_id'] . '@' . CAL_DOMAIN, 'summary' => $o['title'],
            'body' => ['DTSTART;VALUE=DATE:' . str_replace('-', '', $o['scheduled_date'])],
        ]);
    }

    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}
