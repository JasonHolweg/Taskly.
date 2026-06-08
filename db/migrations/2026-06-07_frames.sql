-- Taskly — Migration: Rahmen-Kosmetik (Frame-System)
SET NAMES utf8mb4;
ALTER TABLE tanuki_profile ADD COLUMN equipped_frame_id BIGINT NULL AFTER equipped_outfit_id;

INSERT INTO cosmetics (category, theme, rarity, name, cost_sparks, asset_ref, meta) VALUES
 ('frame','premium','episch','Premium-Rahmen', 300, 'premium', '{"variant":"premium"}'),
 ('frame','japan','legendaer','Samurai-Rahmen', 600, 'samurai', '{"variant":"samurai"}');
