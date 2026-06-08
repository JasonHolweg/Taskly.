-- Taskly — Seed: Rahmen-Kosmetik über Kategorien (Frame-System v2)
SET NAMES utf8mb4;
UPDATE cosmetics SET theme='prestige', name='Premium' WHERE category='frame' AND asset_ref='premium';
UPDATE cosmetics SET name='Samurai', rarity='legendaer', theme='japan' WHERE category='frame' AND asset_ref='samurai';
INSERT INTO cosmetics (category,theme,rarity,name,cost_sparks,asset_ref,meta) VALUES
('frame','prestige','selten','Platin',200,'platin','{"variant":"platin"}'),
('frame','prestige','legendaer','Gold',600,'gold','{"variant":"gold"}'),
('frame','japan','episch','Sakura',350,'sakura','{"variant":"sakura"}'),
('frame','japan','selten','Seigaiha',200,'seigaiha','{"variant":"seigaiha"}'),
('frame','japan','episch','Sumi-e',350,'zen','{"variant":"zen"}'),
('frame','cyberpunk','episch','Neon',350,'neon','{"variant":"neon"}'),
('frame','cyberpunk','selten','Matrix',200,'matrix','{"variant":"matrix"}'),
('frame','cyberpunk','legendaer','Hologramm',600,'holo','{"variant":"holo"}'),
('frame','steampunk','episch','Messing',350,'messing','{"variant":"messing"}'),
('frame','steampunk','selten','Kupfer',200,'kupfer','{"variant":"kupfer"}'),
('frame','steampunk','legendaer','Uhrwerk',600,'uhrwerk','{"variant":"uhrwerk"}'),
('frame','blumen','selten','Rose',200,'rose','{"variant":"rose"}'),
('frame','blumen','selten','Lavendel',200,'lavendel','{"variant":"lavendel"}'),
('frame','blumen','episch','Wiese',350,'wiese','{"variant":"wiese"}'),
('frame','helden','episch','Held Lila',350,'held-lila','{"variant":"held-lila"}'),
('frame','helden','legendaer','Held Gold',600,'held-gold','{"variant":"held-gold"}'),
('frame','helden','episch','Held Grün',350,'held-gruen','{"variant":"held-gruen"}');
