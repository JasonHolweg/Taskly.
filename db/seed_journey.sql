-- =====================================================================
-- Taskly v2 — „Tanuki's Adventures" — Seed-Daten für das Reise-System
-- 3 Ziele · 18 Wegpunkte · 17 Event-Vorlagen · 48 Items
-- MySQL 8 · utf8mb4
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Ziele (journey_destinations)
-- ---------------------------------------------------------------------
INSERT INTO journey_destinations (destination, name, total_km, tagline, unlock_level, active) VALUES
('japan',  'Kyoto',        100, 'Folge den Kirschblüten bis in die alte Kaiserstadt. 🌸', 3, 1),
('drache', 'Drachenberg',   80, 'Glutpfade, Höhlen und ein Schatz, der auf dich wartet. 🐉', 5, 1),
('galaxy', 'Sternenhafen', 120, 'Einmal quer durch den Neon-Nebel — Kurs auf die Sterne. 🌌', 7, 1);

-- ---------------------------------------------------------------------
-- 2) Wegpunkte (journey_waypoints)
--    Pro Strecke: gemischte Belohnungen, genau EINE Lootbox vor dem
--    Ziel, das Ziel selbst ist immer die Themen-Lootbox (Gratis-Zug).
-- ---------------------------------------------------------------------

-- Japan / Kyoto (100 km)
INSERT INTO journey_waypoints (destination, name, at_km, reward_type, reward_ref, reward_amount, flavor) VALUES
('japan', 'Sakura-Wald',         20, 'sparks',  NULL,    20, 'Rosa Blüten rieseln auf dein Fell. Tanuki bleibt kurz stehen — nur zum Staunen. 🌸'),
('japan', 'Verlassener Schrein', 40, 'item',    'japan',  1, 'Zwischen alten Torii liegt etwas, das hier auf dich gewartet hat.'),
('japan', 'Versteckte Kiste',    60, 'lootbox', 'japan',  1, 'Unter Moos und Laub: eine Kiste! Wer suchet, der findet. 🎁'),
('japan', 'Tempelstadt',         70, 'sparks',  NULL,    40, 'Glocken läuten, Laternen glühen. Die Stadt steckt dir ein paar Sparks zu.'),
('japan', 'Drachenberg-Pass',    80, 'item',    'japan',  1, 'Der Wind oben am Pass trägt dir ein Fundstück direkt vor die Pfoten.'),
('japan', 'Kyoto',              100, 'lootbox', 'japan',  1, 'Du bist da! Kyoto öffnet dir seine Tore — und seine Schatzkiste. ⛩️');

-- Drache / Drachenberg (80 km)
INSERT INTO journey_waypoints (destination, name, at_km, reward_type, reward_ref, reward_amount, flavor) VALUES
('drache', 'Funkengrotte',       15, 'sparks',  NULL,    15, 'Die Wände glitzern wie tausend kleine Sterne. Ein paar Funken nimmst du mit. ✨'),
('drache', 'Glutpfad',           30, 'item',    'drache', 1, 'Heiße Steine, vorsichtige Pfoten — und mittendrin ein Fundstück.'),
('drache', 'Alte Schatzkammer',  45, 'lootbox', 'drache', 1, 'Eine Kammer voller Kisten. Eine davon gehört jetzt dir. 🗝️'),
('drache', 'Echohöhle',          60, 'sparks',  NULL,    45, 'Dein Ruf hallt zehnfach zurück — und mit ihm klimpern Sparks aus der Dunkelheit.'),
('drache', 'Glühender Grat',     70, 'item',    'drache', 1, 'Kurz vor dem Gipfel: etwas Warmes funkelt im Fels.'),
('drache', 'Drachenberg-Gipfel', 80, 'lootbox', 'drache', 1, 'Ganz oben. Der Drache nickt dir zu und schiebt dir seine Kiste rüber. 🐉');

-- Galaxy / Sternenhafen (120 km)
INSERT INTO journey_waypoints (destination, name, at_km, reward_type, reward_ref, reward_amount, flavor) VALUES
('galaxy', 'Station Hoshi-1',          20, 'sparks',  NULL,    20, 'Erster Halt im All. Die Crew winkt — und wirft dir Sparks durch die Luke. 🛰️'),
('galaxy', 'Nebelfeld Murasaki',       45, 'item',    'galaxy', 1, 'Im violetten Nebel treibt etwas Glänzendes. Zugreifen!'),
('galaxy', 'Treibende Frachtkiste',    70, 'lootbox', 'galaxy', 1, 'Eine herrenlose Kiste schwebt vorbei. Finderlohn: alles drin. 📦'),
('galaxy', 'Neon-Asteroidengürtel',    90, 'sparks',  NULL,    50, 'Leuchtende Brocken, soweit das Auge reicht. Ein paar Sparks bleiben an dir hängen.'),
('galaxy', 'Kometenschweif-Brücke',   105, 'item',    'galaxy', 1, 'Du surfst über einen Kometenschweif — und fängst dabei etwas Seltenes.'),
('galaxy', 'Sternenhafen',            120, 'lootbox', 'galaxy', 1, 'Andocken, durchatmen, Kiste öffnen. Willkommen im Sternenhafen! 🌌');

-- ---------------------------------------------------------------------
-- 3) Event-Vorlagen (journey_event_templates)
--    reward_type 'distance' = Meter!
-- ---------------------------------------------------------------------

-- Japan
INSERT INTO journey_event_templates (destination, kind, label, reward_type, reward_min, reward_max, weight) VALUES
('japan', 'statue', '🗿 Uralte Statue',        'sparks',     10,   30, 3),
('japan', 'monk',   '🚶 Wandernder Mönch',     'distance', 1000, 3000, 3),
('japan', 'hidden', '❓ Etwas raschelt im Bambus', 'item',      1,    1, 1),
('japan', 'onsen',  '♨️ Heiße Quelle',          'distance', 1500, 3000, 2),
('japan', 'koi',    '🎏 Stiller Koi-Teich',     'sparks',     15,   25, 2),
('japan', 'teehaus','🍵 Teehaus am Wegesrand',  'sparks',     10,   20, 2);

-- Drache
INSERT INTO journey_event_templates (destination, kind, label, reward_type, reward_min, reward_max, weight) VALUES
('drache', 'statue',    '🗿 Versteinerter Wächter',   'sparks',     10,   30, 3),
('drache', 'monk',      '🧙 Alter Bergeremit',        'distance', 1000, 3000, 3),
('drache', 'hidden',    '❓ Etwas glüht in der Spalte','item',       1,    1, 1),
('drache', 'drachenei', '🥚 Warmes Drachenei',        'sparks',     20,   30, 2),
('drache', 'thermik',   '🌬️ Heiße Thermik',           'distance', 1500, 3000, 2),
('drache', 'goldader',  '⛏️ Funkelnde Goldader',      'sparks',     15,   25, 1);

-- Galaxy
INSERT INTO journey_event_templates (destination, kind, label, reward_type, reward_min, reward_max, weight) VALUES
('galaxy', 'statue',     '🗿 Antiker Monolith',          'sparks',     10,   30, 3),
('galaxy', 'monk',       '🧑‍🚀 Treibender Astronaut',     'distance', 1000, 3000, 3),
('galaxy', 'hidden',     '❓ Unbekanntes Signal',         'item',       1,    1, 1),
('galaxy', 'komet',      '☄️ Vorbeiziehender Komet',     'distance', 1500, 3000, 2),
('galaxy', 'sternschnuppe','🌠 Sternschnuppen-Regen',    'sparks',     15,   25, 2);

-- ---------------------------------------------------------------------
-- 4) Items — 16 pro Thema
--    value-Bänder: gewoehnlich ~0.05 · selten ~0.10 · episch ~0.18 · legendaer ~0.25
-- ---------------------------------------------------------------------

-- Japan (16)
INSERT INTO items (name, type, value, rarity, theme, asset_ref, flavor) VALUES
('Geta-Sandalen',            'speed', 0.05, 'gewoehnlich', 'japan', 'items/japan/geta-sandalen.png',            'Klack, klack — mit Holzsandalen läuft sich der Weg gleich leichter.'),
('Onigiri',                  'speed', 0.06, 'gewoehnlich', 'japan', 'items/japan/onigiri.png',                  'Ein Reisbällchen für unterwegs. Voller Bauch, flotte Pfoten.'),
('Tabi-Socken',              'speed', 0.10, 'selten',      'japan', 'items/japan/tabi-socken.png',              'Leise wie ein Ninja, schnell wie der Wind.'),
('Sturmfächer',              'speed', 0.18, 'episch',      'japan', 'items/japan/sturmfaecher.png',             'Ein Schwung, und der Rückenwind gehört dir.'),
('Wolkenschuhe des Tengu',   'speed', 0.27, 'legendaer',   'japan', 'items/japan/wolkenschuhe-des-tengu.png',   'Der Tengu lieh sie nur ungern her. Du läufst praktisch über Wolken.'),
('Glücksmünze',              'loot',  0.05, 'gewoehnlich', 'japan', 'items/japan/gluecksmuenze.png',            'Eine Fünf-Yen-Münze mit Loch. Glück, das in jede Tasche passt.'),
('Sakura-Haarnadel',         'loot',  0.09, 'selten',      'japan', 'items/japan/sakura-haarnadel.png',         'Wer Blüten im Fell trägt, dem fällt das Glück leichter zu.'),
('Omamori-Amulett',          'loot',  0.11, 'selten',      'japan', 'items/japan/omamori-amulett.png',          'Ein Schutzamulett vom Schrein. Nicht öffnen — einfach wirken lassen.'),
('Torii-Anhänger',           'loot',  0.17, 'episch',      'japan', 'items/japan/torii-anhaenger.png',          'Ein kleines rotes Tor, durch das das Glück direkt zu dir findet.'),
('Maneki-Neko',              'loot',  0.19, 'episch',      'japan', 'items/japan/maneki-neko.png',              'Die Winkekatze winkt — und Schätze kommen näher.'),
('Tanuki-Glücksstatue',      'loot',  0.26, 'legendaer',   'japan', 'items/japan/tanuki-gluecksstatue.png',     'Ein Tanuki aus Stein, der dir zuzwinkert. Das größte Glück erkennt sich selbst.'),
('Fūrin-Windspiel',          'xp',    0.04, 'gewoehnlich', 'japan', 'items/japan/fuurin-windspiel.png',         'Sein Klingeln macht den Kopf klar und jede Aufgabe ein bisschen wertvoller.'),
('Daruma-Figur',             'xp',    0.05, 'gewoehnlich', 'japan', 'items/japan/daruma-figur.png',             'Ein Auge ist schon ausgemalt. Das zweite wartet auf dein nächstes Ziel.'),
('Ema-Täfelchen',            'xp',    0.10, 'selten',      'japan', 'items/japan/ema-taefelchen.png',           'Dein Wunsch hängt am Schrein — und treibt dich ein Stück weiter.'),
('Kalligrafie-Pinsel',       'xp',    0.18, 'episch',      'japan', 'items/japan/kalligrafie-pinsel.png',       'Wer schön schreibt, lernt doppelt. Sagt zumindest der Meister.'),
('Fuji-Talisman',            'xp',    0.28, 'legendaer',   'japan', 'items/japan/fuji-talisman.png',            'Ein Splitter vom heiligen Berg. Jede erledigte Aufgabe wiegt schwerer.');

-- Drache (16)
INSERT INTO items (name, type, value, rarity, theme, asset_ref, flavor) VALUES
('Feuerstein',               'speed', 0.04, 'gewoehnlich', 'drache', 'items/drache/feuerstein.png',              'Warm in der Pfote, Feuer unterm Hintern.'),
('Schuppenstiefel',          'speed', 0.05, 'gewoehnlich', 'drache', 'items/drache/schuppenstiefel.png',         'Drachenschuppen an den Sohlen — kein Glutpfad zu heiß.'),
('Windschwingen-Umhang',     'speed', 0.10, 'selten',      'drache', 'items/drache/windschwingen-umhang.png',    'Flattert im Bergwind und zieht dich sanft voran.'),
('Glutschweif-Sporen',       'speed', 0.19, 'episch',      'drache', 'items/drache/glutschweif-sporen.png',      'Hinterlassen eine glühende Spur. Sieht schnell aus. Ist es auch.'),
('Drachenflug-Schwingen',    'speed', 0.28, 'legendaer',   'drache', 'items/drache/drachenflug-schwingen.png',   'Kurz abheben zählt nicht als Schummeln, oder?'),
('Kupferschuppe',            'loot',  0.05, 'gewoehnlich', 'drache', 'items/drache/kupferschuppe.png',           'Die kleinste Schuppe vom Hort. Der Drache vermisst sie nicht. Hoffentlich.'),
('Schuppenamulett',          'loot',  0.10, 'selten',      'drache', 'items/drache/schuppenamulett.png',         'Wer Drachenschuppen trägt, findet Schätze leichter.'),
('Goldschuppe',              'loot',  0.11, 'selten',      'drache', 'items/drache/goldschuppe.png',             'Glänzt so schön, dass andere Schätze neugierig näher rücken.'),
('Drachenkrallen-Talisman',  'loot',  0.18, 'episch',      'drache', 'items/drache/drachenkrallen-talisman.png', 'Eine Kralle, die nach Gold zeigt. Praktisch.'),
('Hortkrone des Drachenkönigs','loot',0.27, 'legendaer',   'drache', 'items/drache/hortkrone-des-drachenkoenigs.png', 'Wer sie trägt, dem gehört der halbe Hort. Sagt das Kleingedruckte.'),
('Lavaperle',                'xp',    0.06, 'gewoehnlich', 'drache', 'items/drache/lavaperle.png',               'Glüht von innen — so wie du nach einer erledigten Aufgabe.'),
('Glühende Asche',           'xp',    0.05, 'gewoehnlich', 'drache', 'items/drache/gluehende-asche.png',         'Aus Asche wird Glut, aus Glut wird Schwung.'),
('Drachenei-Splitter',       'xp',    0.09, 'selten',      'drache', 'items/drache/drachenei-splitter.png',      'Ein Stück Schale, in dem noch ein Funke Wachstum steckt.'),
('Glutkern',                 'xp',    0.11, 'selten',      'drache', 'items/drache/glutkern.png',                'Das warme Herz eines erloschenen Feuers. Gibt jeder Tat mehr Gewicht.'),
('Drachenzahn',              'xp',    0.18, 'episch',      'drache', 'items/drache/drachenzahn.png',             'Freiwillig abgegeben, versichert der Drache. Beißt sich durch jede Aufgabe.'),
('Drachenherz',              'xp',    0.29, 'legendaer',   'drache', 'items/drache/drachenherz.png',             'Schlägt im Takt deiner Erfolge. Jeder Sieg zählt doppelt schön.');

-- Galaxy (16)
INSERT INTO items (name, type, value, rarity, theme, asset_ref, flavor) VALUES
('Schubdüse',                'speed', 0.05, 'gewoehnlich', 'galaxy', 'items/galaxy/schubduese.png',              'Klein, aber zuverlässig. Pfff — und schon bist du weiter.'),
('Astro-Helm',               'speed', 0.06, 'gewoehnlich', 'galaxy', 'items/galaxy/astro-helm.png',              'Windschnittig, weltraumtauglich und steht dir ausgezeichnet.'),
('Ionentriebwerk',           'speed', 0.11, 'selten',      'galaxy', 'items/galaxy/ionentriebwerk.png',          'Leuchtet blau und schiebt leise, aber gewaltig an.'),
('Pulsar-Antenne',           'speed', 0.17, 'episch',      'galaxy', 'items/galaxy/pulsar-antenne.png',          'Empfängt den Takt der Sterne — und du läufst im Rhythmus mit.'),
('Plasma-Booster',           'speed', 0.18, 'episch',      'galaxy', 'items/galaxy/plasma-booster.png',          'Vorsicht, heiß. Und schnell. Vor allem schnell.'),
('Warpkern',                 'speed', 0.28, 'legendaer',   'galaxy', 'items/galaxy/warpkern.png',                'Faltet den Weg einfach zusammen. Physik ist verhandelbar.'),
('Sternenstaub-Beutel',      'loot',  0.05, 'gewoehnlich', 'galaxy', 'items/galaxy/sternenstaub-beutel.png',     'Eine Prise Sternenstaub macht jeden Fund ein bisschen funkelnder.'),
('Holo-Würfel',              'loot',  0.09, 'selten',      'galaxy', 'items/galaxy/holo-wuerfel.png',            'Zeigt dir flimmernd, wo sich das Suchen lohnt.'),
('Sternenkarte',             'loot',  0.10, 'selten',      'galaxy', 'items/galaxy/sternenkarte.png',            'X markiert den Schatz. Im All gibt es erstaunlich viele X.'),
('Asteroiden-Magnet',        'loot',  0.19, 'episch',      'galaxy', 'items/galaxy/asteroiden-magnet.png',       'Zieht Wertvolles an. Bitte nicht neben die Bordkasse legen.'),
('Nebelherz',                'loot',  0.27, 'legendaer',   'galaxy', 'items/galaxy/nebelherz.png',               'Der leuchtende Kern eines Nebels. Schätze finden dich von selbst.'),
('Mondkiesel',               'xp',    0.05, 'gewoehnlich', 'galaxy', 'items/galaxy/mondkiesel.png',              'Ein Souvenir vom Mond. Macht jede Aufgabe ein kleines bisschen größer.'),
('Satelliten-Anhänger',      'xp',    0.04, 'gewoehnlich', 'galaxy', 'items/galaxy/satelliten-anhaenger.png',    'Piept aufmunternd, wenn du etwas erledigst.'),
('Kometensplitter',          'xp',    0.10, 'selten',      'galaxy', 'items/galaxy/kometensplitter.png',         'Noch warm vom Flug. Verleiht deinen Taten Schweif.'),
('Neon-Datenkristall',       'xp',    0.17, 'episch',      'galaxy', 'items/galaxy/neon-datenkristall.png',      'Speichert deine Erfolge — und gibt sie mit Zinsen zurück.'),
('Supernova-Fragment',       'xp',    0.30, 'legendaer',   'galaxy', 'items/galaxy/supernova-fragment.png',      'Der Rest eines explodierten Sterns. Deine Erfolge strahlen heller.');
