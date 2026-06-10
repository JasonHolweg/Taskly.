-- Taskly — Migration: Tanuki's Adventures (v2) — journey.md §9
-- (Reise-System: Ausdauer, Ziele, Wegpunkte, Events, Items; v1 bleibt unberührt —
--  einzige Ausnahme: tanuki_profile bekommt neue Spalten, wie in v1 reserviert)
SET NAMES utf8mb4;

-- Ausdauer & Item-Bonus-Cache am Tanuki (journey.md §2/§3)
-- Abweichung ggü. journey.md §9: stamina/stamina_max in METERN als INT
-- (statt SMALLINT-Einheiten) — exakte Integer-Mathematik beim passiven Tick.
ALTER TABLE tanuki_profile
  ADD COLUMN stamina     INT DEFAULT 0,            -- Treibstoff in Metern
  ADD COLUMN stamina_max INT DEFAULT 12000,        -- 12 km
  ADD COLUMN speed_bonus DECIMAL(4,2) DEFAULT 0,   -- aus equipped Items aggregiert (Cache)
  ADD COLUMN loot_bonus  DECIMAL(4,2) DEFAULT 0,
  ADD COLUMN xp_bonus    DECIMAL(4,2) DEFAULT 0;   -- wirkt NUR auf Reise-Distanz, nie auf echte XP

-- Ziel-Katalog (Zusatz ggü. journey.md §9: datengetriebener Content statt Hardcode)
CREATE TABLE IF NOT EXISTS journey_destinations (
  destination  VARCHAR(60) PRIMARY KEY,            -- Slug = Thema, z.B. 'japan','drache','galaxy'
  name         VARCHAR(120),
  total_km     INT,
  tagline      VARCHAR(200),
  unlock_level SMALLINT DEFAULT 3,
  active       BOOLEAN DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aktive/abgeschlossene Reisen pro User
CREATE TABLE IF NOT EXISTS journeys (
  id           BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id      BIGINT NOT NULL,
  destination  VARCHAR(60),                        -- = Thema (Slug aus journey_destinations)
  total_km     INT,
  distance_m   INT DEFAULT 0,                      -- Fortschritt in Metern
  boost_pct    TINYINT DEFAULT 0,
  boost_until  DATETIME,
  status       ENUM('active','done') DEFAULT 'active',
  started_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME,
  last_tick_at DATETIME,                           -- Anker für deterministische passive Bewegung — Zusatz ggü. journey.md
  CONSTRAINT fk_journey_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_journey_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wegpunkte (Config pro Ziel; journey.md §4)
CREATE TABLE IF NOT EXISTS journey_waypoints (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  destination   VARCHAR(60),
  name          VARCHAR(120),
  at_km         INT,
  reward_type   ENUM('sparks','lootbox','item'),
  reward_ref    VARCHAR(120),
  reward_amount INT,
  flavor        VARCHAR(200),
  INDEX idx_wp_dest (destination, at_km)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zufalls-Event-Vorlagen pro Ziel (journey.md §5; Zusatz ggü. §9, dort nur implizit)
CREATE TABLE IF NOT EXISTS journey_event_templates (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  destination VARCHAR(60),
  kind        VARCHAR(60),                         -- 'statue','monk','hidden' ...
  label       VARCHAR(120),
  reward_type ENUM('sparks','distance','item'),
  reward_min  INT,
  reward_max  INT,
  weight      TINYINT DEFAULT 1,
  INDEX idx_evt_dest (destination)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reise-Items (journey.md §3) — getrennt von Kosmetik (cosmetics bleibt unberührt)
CREATE TABLE IF NOT EXISTS items (
  id        BIGINT PRIMARY KEY AUTO_INCREMENT,
  name      VARCHAR(120),
  type      ENUM('speed','loot','xp'),
  value     DECIMAL(4,2),                          -- z.B. 0.10 = +10 %
  rarity    ENUM('gewoehnlich','selten','episch','legendaer'),
  theme     VARCHAR(60),
  asset_ref VARCHAR(255),
  flavor    VARCHAR(200),
  INDEX idx_items_pool (theme, rarity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item-Inventar pro User
CREATE TABLE IF NOT EXISTS user_items (
  user_id     BIGINT,
  item_id     BIGINT,
  equipped    BOOLEAN DEFAULT 0,
  acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, item_id),
  CONSTRAINT fk_ui_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ui_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events pro Reise: vorab gewürfelt + Log von Wegpunkt-Claims/Ankunft
CREATE TABLE IF NOT EXISTS journey_events (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  journey_id    BIGINT NOT NULL,
  kind          VARCHAR(60),                       -- 'statue','monk','hidden','waypoint','arrival'
  label         VARCHAR(120),
  reward_type   ENUM('sparks','distance','item','lootbox'),
  reward_amount INT,
  reward_ref    VARCHAR(120),
  at_km         INT,
  discovered    TINYINT DEFAULT 0,
  discovered_at DATETIME NULL DEFAULT NULL,        -- abweichend von journey.md — geplante Events brauchen NULL
  CONSTRAINT fk_je_journey FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
  INDEX idx_je_journey (journey_id, discovered, at_km)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
