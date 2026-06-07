<?php
/**
 * Taskly — Claude-API-Anbindung (architecture.md §4).
 * Brain-Dump-Parsing (Sonnet) + optionale „Was jetzt?"-Auswahl (Haiku).
 * Alles mit Fallback: ohne API-Key bleibt der Kern-Loop funktionsfähig.
 */
declare(strict_types=1);

function claude_available(): bool
{
    global $CONFIG;
    return trim((string) ($CONFIG['anthropic']['api_key'] ?? '')) !== '';
}

/** Roh-Call an die Messages-API. Gibt den Text-Content oder null bei Fehler. */
function claude_call(string $model, string $system, string $userText, int $maxTokens = 1500): ?string
{
    global $CONFIG;
    if (!claude_available()) {
        return null;
    }
    $a = $CONFIG['anthropic'];

    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $userText]],
    ];

    $ch = curl_init($a['base_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => (int) ($a['timeout'] ?? 30),
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $a['api_key'],
            'anthropic-version: ' . $a['version'],
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        return null;
    }
    $data = json_decode($resp, true);
    return $data['content'][0]['text'] ?? null;
}

/** JSON aus einer Claude-Antwort extrahieren (Code-Fences/Prosa tolerieren). */
function extract_json(string $text): ?array
{
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start === false || $end === false) {
        return null;
    }
    $json = substr($text, $start, $end - $start + 1);
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Brain-Dump → strukturierte Tasks (architecture.md §4.1).
 * Gibt ein Array von Task-Strukturen zurück (immer mindestens ein Versuch).
 */
function parse_braindump(string $input): array
{
    global $CONFIG;
    $input = trim($input);
    if ($input === '') {
        return [];
    }

    $system = <<<SYS
Du bist der Parser von Taskly, einer ADHS-Aufgaben-App. Wandle den Brain-Dump des Users
in strukturierte Aufgaben um. Antworte mit STRIKTEM JSON, KEIN Prosa-Text, KEINE Code-Fences.

Format:
{ "tasks": [
  { "title": "kurzer Titel",
    "type": "flexible|deadline|termin",
    "domain": "haushalt|privat|business|termin",
    "time_estimate": <Minuten, Ganzzahl>,
    "energy": "niedrig|mittel|hoch",
    "context": "zuhause|unterwegs|egal",
    "priority": <1|2|3>,
    "recurrence_rule": "<iCal-RRULE oder null>",
    "due_at": "<YYYY-MM-DD HH:MM:SS oder null, nur bei deadline>",
    "fixed_at": "<YYYY-MM-DD HH:MM:SS oder null, nur bei termin>",
    "base_xp": <Ganzzahl>
  }
] }

Regeln für base_xp (XP = Zeit-Basis × Widerstands-Faktor, gerundet auf 5):
- Zeit-Basis: ≤5 Min=10, ~15 Min=20, ~30 Min=40, 60+ Min=60.
- Widerstand: leicht/mag man ×1.0, neutral ×1.2, ungeliebt/wird aufgeschoben ×1.5.
- Schätze den Widerstand aus Ton & Art der Aufgabe. Termine bekommen base_xp 0.
Ton: ruhig, deutsch. Erfinde keine Aufgaben, die nicht im Text stehen.
SYS;

    $text = claude_call($CONFIG['anthropic']['model_parse'], $system, $input, 2000);
    if ($text !== null) {
        $data = extract_json($text);
        if ($data && !empty($data['tasks']) && is_array($data['tasks'])) {
            return array_map('normalize_task', $data['tasks']);
        }
    }

    // Fallback ohne KI: jede Zeile/Teil wird eine einfache flexible Aufgabe.
    return braindump_fallback($input);
}

/** Heuristischer Parser ohne KI. */
function braindump_fallback(string $input): array
{
    $parts = preg_split('/[\n;]+|,\s+| und /u', $input);
    $tasks = [];
    foreach ($parts as $p) {
        $title = trim($p);
        if ($title === '' || mb_strlen($title) < 2) {
            continue;
        }
        $tasks[] = normalize_task([
            'title'         => mb_substr($title, 0, 200),
            'type'          => 'flexible',
            'domain'        => 'privat',
            'time_estimate' => 15,
            'energy'        => 'mittel',
            'context'       => 'egal',
            'priority'      => 2,
            'base_xp'       => compute_base_xp(15, 'neutral'),
        ]);
    }
    return $tasks;
}

/** Task-Struktur säubern + Defaults setzen, bevor sie in die DB geht. */
function normalize_task(array $t): array
{
    $types   = ['flexible', 'deadline', 'termin'];
    $domains = ['haushalt', 'privat', 'business', 'termin'];
    $energy  = ['niedrig', 'mittel', 'hoch'];
    $context = ['zuhause', 'unterwegs', 'egal'];

    $type = in_array($t['type'] ?? '', $types, true) ? $t['type'] : 'flexible';
    $mins = (int) ($t['time_estimate'] ?? 15);
    if ($mins <= 0) {
        $mins = 15;
    }

    $baseXp = isset($t['base_xp']) ? (int) $t['base_xp'] : compute_base_xp($mins, 'neutral');
    if ($type === 'termin') {
        $baseXp = 0;
    }

    return [
        'title'           => mb_substr(trim((string) ($t['title'] ?? 'Aufgabe')), 0, 200),
        'notes'           => isset($t['notes']) ? (string) $t['notes'] : null,
        'type'            => $type,
        'domain'          => in_array($t['domain'] ?? '', $domains, true) ? $t['domain'] : 'privat',
        'time_estimate'   => $mins,
        'energy'          => in_array($t['energy'] ?? '', $energy, true) ? $t['energy'] : 'mittel',
        'context'         => in_array($t['context'] ?? '', $context, true) ? $t['context'] : 'egal',
        'priority'        => max(1, min(3, (int) ($t['priority'] ?? 2))),
        'recurrence_rule' => !empty($t['recurrence_rule']) && $t['recurrence_rule'] !== 'null'
                                ? (string) $t['recurrence_rule'] : null,
        'due_at'          => ($type === 'deadline' && !empty($t['due_at']) && $t['due_at'] !== 'null')
                                ? date('Y-m-d H:i:s', strtotime((string) $t['due_at'])) : null,
        'fixed_at'        => ($type === 'termin' && !empty($t['fixed_at']) && $t['fixed_at'] !== 'null')
                                ? date('Y-m-d H:i:s', strtotime((string) $t['fixed_at'])) : null,
        'base_xp'         => $baseXp,
    ];
}
