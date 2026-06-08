SET NAMES utf8mb4;
INSERT INTO cosmetics (category,theme,rarity,name,cost_sparks,asset_ref,meta) VALUES
('tanuki_outfit','steampunk','episch','Steampunk',NULL,'steampunk','{"slug":"steampunk","poses":{"happy":"steampunk-happy.png","celebrate":"steampunk-celebrate.png","sad":"steampunk-sad.png"}}'),
('tanuki_outfit','weltraum','episch','Astronaut',NULL,'astronaut','{"slug":"astronaut","poses":{"happy":"astronaut-happy.png","celebrate":"astronaut-celebrate.png","sad":"astronaut-sad.png"}}'),
('tanuki_outfit','blumen','episch','Blumenkind',NULL,'blumen','{"slug":"blumen","poses":{"happy":"blumen-happy.png","celebrate":"blumen-celebrate.png","sad":"blumen-sad.png"}}'),
('tanuki_outfit','japan','episch','Hanami-Kimono',NULL,'hanami','{"slug":"hanami","poses":{"happy":"hanami-happy.png","celebrate":"hanami-celebrate.png","sad":"hanami-sad.png"}}'),
('tanuki_outfit','cyberpunk','legendaer','Cyberpunk',NULL,'cyberpunk','{"slug":"cyberpunk","poses":{"happy":"cyberpunk-happy.png","celebrate":"cyberpunk-celebrate.png","sad":"cyberpunk-sad.png"}}');
INSERT INTO lootboxes (theme,name,cost_sparks,active) VALUES
('cyberpunk','Cyberpunk-Kiste',120,1),('steampunk','Steampunk-Kiste',120,1),('blumen','Blumen-Kiste',120,1),
('helden','Helden-Kiste',120,1),('drache','Drachen-Kiste',120,1),('galaxy','Galaxy-Kiste',120,1),
('weltraum','Weltraum-Kiste',120,1),('frost','Frost-Kiste',120,1),('ozean','Ozean-Kiste',120,1);
UPDATE cosmetics SET cost_sparks=NULL WHERE category='frame'
  AND theme IN ('japan','helden','cyberpunk','steampunk','frost','ozean','blumen','galaxy','drache');
