-- Taskly — Migration: Item-Bonus-Typen erweitert (Jasons Wunsch)
-- Neu: 'sparks' (✦-Bonus pro Erledigung) und 'stamina' (mehr Ausdauer pro Erledigung).
-- 'xp' bedeutet ab jetzt ECHTEN XP-Bonus (multipliziert nur reale Erledigungen —
-- journey.md §3 erlaubt das explizit; die eiserne Regel bleibt intakt).
SET NAMES utf8mb4;

ALTER TABLE items MODIFY COLUMN type ENUM('speed','loot','xp','sparks','stamina');

ALTER TABLE tanuki_profile
  ADD COLUMN sparks_bonus  DECIMAL(4,2) DEFAULT 0,   -- Cache aus equipped Items
  ADD COLUMN stamina_bonus DECIMAL(4,2) DEFAULT 0;

-- 12 bestehende Items themen-logisch umtypen (Raritäten/Werte bleiben):
UPDATE items SET type='sparks'  WHERE theme='japan'  AND name IN ('Glücksmünze','Maneki-Neko');
UPDATE items SET type='stamina' WHERE theme='japan'  AND name IN ('Onigiri','Fūrin-Windspiel');
UPDATE items SET type='sparks'  WHERE theme='drache' AND name IN ('Goldschuppe','Hortkrone des Drachenkönigs');
UPDATE items SET type='stamina' WHERE theme='drache' AND name IN ('Glühende Asche','Glutkern');
UPDATE items SET type='sparks'  WHERE theme='galaxy' AND name IN ('Sternenstaub-Beutel','Asteroiden-Magnet');
UPDATE items SET type='stamina' WHERE theme='galaxy' AND name IN ('Astro-Helm','Kometensplitter');
