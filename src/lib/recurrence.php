<?php
/**
 * Taskly — minimaler iCal-RRULE-Expander (architecture.md §8.4).
 * Deckt die gängigen Fälle ab, die der Brain-Dump-Parser erzeugt:
 * FREQ=DAILY | WEEKLY[;BYDAY=MO,..] | MONTHLY[;BYMONTHDAY=n].
 */
declare(strict_types=1);

const BYDAY_MAP = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

/** RRULE in einen assoziativen Array parsen. */
function parse_rrule(string $rule): array
{
    $rule = strtoupper(trim($rule));
    if (strncmp($rule, 'RRULE:', 6) === 0) {
        $rule = substr($rule, 6);   // optionales iCal-Präfix entfernen
    }
    $out = [];
    foreach (explode(';', $rule) as $part) {
        if (strpos($part, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $part, 2);
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Konkrete Termine einer RRULE im Fenster [start, end] (beide inkl.).
 * $anchor = Bezugstag (z.B. created_at) für FREQ ohne BY-Teil.
 * Gibt Y-m-d-Strings zurück.
 */
function expand_rrule(string $rule, DateTimeImmutable $start, DateTimeImmutable $end, ?DateTimeImmutable $anchor = null): array
{
    $r = parse_rrule($rule);
    $freq = $r['FREQ'] ?? '';
    if ($freq === '') {
        return [];
    }
    $anchor = $anchor ?? $start;

    // BYDAY-Wochentage (als 1..7)
    $byday = [];
    if (!empty($r['BYDAY'])) {
        foreach (explode(',', $r['BYDAY']) as $d) {
            $d = preg_replace('/[^A-Z]/', '', $d); // evtl. "2MO" → "MO"
            if (isset(BYDAY_MAP[$d])) {
                $byday[] = BYDAY_MAP[$d];
            }
        }
    }
    $bymonthday = !empty($r['BYMONTHDAY']) ? (int) $r['BYMONTHDAY'] : null;

    $dates = [];
    $day = $start;
    while ($day <= $end) {
        $include = false;
        $wd  = (int) $day->format('N');   // 1=Mo
        $dom = (int) $day->format('j');

        switch ($freq) {
            case 'DAILY':
                $include = true;
                break;
            case 'WEEKLY':
                $include = $byday ? in_array($wd, $byday, true) : ($wd === (int) $anchor->format('N'));
                break;
            case 'MONTHLY':
                $target = $bymonthday ?? (int) $anchor->format('j');
                $include = ($dom === $target);
                break;
        }
        if ($include) {
            $dates[] = $day->format('Y-m-d');
        }
        $day = $day->modify('+1 day');
    }
    return $dates;
}
