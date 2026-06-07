-- Taskly — Seed: Tanuki-Outfit-Kosmetik + Lootboxen
-- Generiert aus public/assets/img/tanuki/catalog.json (architecture.md §2, rules.md §3).
-- Idempotent: leert cosmetics/lootboxes vor dem Einspielen (kein User besitzt noch Items).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE user_cosmetics;
TRUNCATE TABLE lootbox_openings;
TRUNCATE TABLE cosmetics;
TRUNCATE TABLE lootboxes;
SET FOREIGN_KEY_CHECKS=1;

INSERT INTO lootboxes (theme, name, cost_sparks, active) VALUES
  ('japan',   'Japan-Kiste',  120, 1),
  ('kostuem', 'Kostüm-Kiste', 120, 1);

INSERT INTO cosmetics (category, theme, rarity, name, cost_sparks, asset_ref, meta) VALUES
  ('tanuki_outfit', 'japan', 'gewoehnlich', 'Festival-Happi (grün)', NULL, 'happi-green', '{"slug": "happi-green", "poses": {"happy": "happi-green-happy.png", "celebrate": "happi-green-celebrate.png", "sad": "happi-green-sad.png"}}'),
  ('tanuki_outfit', 'kostuem', 'gewoehnlich', 'Dino-Kigurumi', NULL, 'dino', '{"slug": "dino", "poses": {"happy": "dino-happy.png", "celebrate": "dino-celebrate.png", "sad": "dino-sad.png"}}'),
  ('tanuki_outfit', 'japan', 'selten', 'Festival-Happi (blau)', NULL, 'happi-blue', '{"slug": "happi-blue", "poses": {"happy": "happi-blue-happy.png", "celebrate": "happi-blue-celebrate.png", "sad": "happi-blue-sad.png"}}'),
  ('tanuki_outfit', 'japan', 'selten', 'Kimono (grün)', NULL, 'kimono', '{"slug": "kimono", "poses": {"happy": "kimono-happy.png"}}'),
  ('tanuki_outfit', 'japan', 'selten', 'Ninja (grün)', NULL, 'ninja-green', '{"slug": "ninja-green", "poses": {"happy": "ninja-green-happy.png", "celebrate": "ninja-green-celebrate.png", "sad": "ninja-green-sad.png"}}'),
  ('tanuki_outfit', 'japan', 'episch', 'Sakura-Kimono', NULL, 'sakura', '{"slug": "sakura", "poses": {"happy": "sakura-happy.png", "celebrate": "sakura-celebrate.png", "sad": "sakura-sad.png"}}'),
  ('tanuki_outfit', 'japan', 'episch', 'Ninja (schwarz)', NULL, 'ninja-black', '{"slug": "ninja-black", "poses": {"celebrate": "ninja-black-celebrate.png", "sad": "ninja-black-sad.png"}}'),
  ('tanuki_outfit', 'kostuem', 'episch', 'Gentleman', NULL, 'gentleman', '{"slug": "gentleman", "poses": {"happy": "gentleman-happy.png", "celebrate": "gentleman-celebrate.png", "sad": "gentleman-sad.png"}}'),
  ('tanuki_outfit', 'japan', 'legendaer', 'Samurai-Rüstung', NULL, 'samurai', '{"slug": "samurai", "poses": {"happy": "samurai-happy.png", "celebrate": "samurai-celebrate.png", "sad": "samurai-sad.png"}}'),
  ('tanuki_outfit', 'kostuem', 'legendaer', 'Held (lila)', NULL, 'hero-purple', '{"slug": "hero-purple", "poses": {"happy": "hero-purple-happy.png"}}'),
  ('tanuki_outfit', 'kostuem', 'legendaer', 'Held (grün)', NULL, 'hero-green', '{"slug": "hero-green", "poses": {"happy": "hero-green-happy.png"}}');
