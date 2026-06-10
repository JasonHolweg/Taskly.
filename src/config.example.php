<?php
/**
 * Taskly — Konfiguration (Vorlage)
 * Kopiere diese Datei nach `config.php` und trage echte Werte ein.
 * `config.php` ist .gitignore-geschützt und gehört NICHT ins Repo.
 *
 * Alle Stellschrauben (XP, Drop-Rates, Streak …) stehen unter 'tuning'
 * — Quelle der Wahrheit: rules.md §9.
 */

return [
    // --- Datenbank (MySQL) ---
    'db' => [
        'host'    => 'localhost',
        'name'    => 'taskly',
        'user'    => 'taskly',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // --- Claude API ---
    'anthropic' => [
        'api_key'       => '',                       // sk-ant-… (leer = Fallback-Modus)
        'base_url'      => 'https://api.anthropic.com/v1/messages',
        'version'       => '2023-06-01',
        'model_parse'   => 'claude-sonnet-4-6',      // Brain-Dump-Parsing (§4.1)
        'model_select'  => 'claude-haiku-4-5',       // „Was jetzt?" + Wochen-Verteilung (§4.4)
        'timeout'       => 30,
    ],

    // --- Web Push (VAPID) — v1.1, optional ---
    'vapid' => [
        'public_key'  => '',
        'private_key' => '',
        'subject'     => 'mailto:jason.patrick.holweg@gmail.com',
    ],

    // --- App ---
    'app' => [
        'name'     => 'Taskly',
        'base_url' => 'https://taskly.jasonholweg.de',
        'debug'    => false,
    ],

    // --- Stellschrauben (rules.md §9) ---
    'tuning' => [
        // XP: Zeit-Basis nach Minuten (≤5 / ~15 / ~30 / 60+)
        'xp_time_base'      => [5 => 10, 15 => 20, 30 => 40, 999 => 60],
        // Widerstands-Faktor (leicht / neutral / ungeliebt)
        'xp_resistance'     => ['leicht' => 1.0, 'neutral' => 1.2, 'ungeliebt' => 1.5],
        'xp_round_to'       => 5,
        // Level-Kurve: XP für L→L+1 = base + step×(L-1)
        'level_base'        => 100,
        'level_step'        => 50,
        // Glücksumschlag bei Level-Up (Spark-Range, zufällig)
        'levelup_sparks'    => [20, 60],
        // Streak-Meilenstein-Boni (Tage => Sparks)
        'streak_bonus'      => [3 => 20, 7 => 50, 14 => 120, 30 => 300],
        // Lootbox
        'box_price'         => 120,
        'drop_rates'        => ['gewoehnlich' => 60, 'selten' => 27, 'episch' => 10, 'legendaer' => 3],
        'dupe_refund'       => ['gewoehnlich' => 10, 'selten' => 25, 'episch' => 60, 'legendaer' => 150],
        'soft_pity'         => 10,   // Episch+ garantiert spätestens alle N Boxen
        // Streak
        'schontag_recharge' => 7,    // Puffer lädt nach N zusammenhängenden Tagen
        'streak_ice_hours'  => 48,   // Eis-Fenster
        // „Was jetzt?"-Scoring-Gewichte w1..w6
        'select_weights'    => ['w1' => 3, 'w2' => 4, 'w3' => 2, 'w4' => 2, 'w5' => 3, 'w6' => 2],
        'plan_max_per_day'  => 4,    // max geplante flexible Tasks/Tag
        'time_tolerance'    => 1.2,  // Zeit-Filter-Toleranz
    ],

    // --- Tanuki's Adventures (v2) — Stellschrauben (journey.md §10) ---
    'journey' => [
        'enabled'                 => true,   // Feature-Flag: aus = reines v1-Verhalten
        'unlock_level'            => 3,      // Reise ab Level 3 (§8: progressive Freischaltung)
        'shop_unlock_level'       => 4,      // Shop ab Level 4 (§8: Sparks stauen sich → Anticipation)
        'm_per_xp'                => 100,    // 1 XP = 100 m → 40-XP-Task = +4 km Sofort-Schub (§2)
        'passive_kmh'             => 1.0,    // passive Wandergeschwindigkeit ~1 km/h (§2)
        'stamina_max_m'           => 12000,  // Ausdauer-Deckel: max 12 km idle zwischen zwei Aufgaben (§2)
        'stamina_per_task_m'      => 1000,   // +1 km Treibstoff pro erledigter Aufgabe (§2)
        'equip_slots'             => 3,      // Item-Slots: Setup wählen statt Tabellen-Spiel (§3)
        'boost_pct'               => 15,     // Reise-Boost +15 % — verdient, kein Echtgeld (§7)
        'boost_hours'             => 24,     // Boost-Dauer in Stunden (§7)
        'event_count'             => 3,      // verdeckte Zufalls-Events pro Reise (§5)
        'event_min_gap_km'        => 5,      // Mindestabstand der Events auf der Strecke
        'arrival_fallback_sparks' => 120,    // Theme komplett: Gratis-Zug wird zu Sparks (~1 Boxpreis)
        'item_fallback_sparks'    => 25,     // alle Reise-Items besessen: kleiner Sparks-Trost
    ],
];
