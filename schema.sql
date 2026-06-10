-- =====================================================================
--  Taskly — Datenbankschema v1
--  by Jason Holweg · taskly.jasonholweg.de
--  MySQL 8.x · InnoDB · utf8mb4 (Emoji-safe)
--
--  Quelle der Wahrheit: architecture.md §2, rules.md §3–§4
--  Einspielen:  mysql -u <user> -p <db> < schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
--  FAMILIE / ACCOUNTS
-- ---------------------------------------------------------------------

CREATE TABLE households (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(120),
  invite_code CHAR(8) UNIQUE,                       -- Familien-Beitritt
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id             BIGINT PRIMARY KEY AUTO_INCREMENT,
  household_id   BIGINT NOT NULL,
  name           VARCHAR(80),
  email          VARCHAR(190) UNIQUE,
  password_hash  VARCHAR(255),                      -- Argon2id
  whatsapp_phone VARCHAR(20),                        -- E.164, für v1.1-Nudges
  push_sub       JSON,                               -- Web-Push Subscription
  nudge_prefs    JSON,                               -- {"channel":"push","windows":["12:00","18:00"]}
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_household
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  AUFGABEN  —  Definition (tasks) vs. Vorkommen (task_occurrences)
-- ---------------------------------------------------------------------

CREATE TABLE tasks (
  id              BIGINT PRIMARY KEY AUTO_INCREMENT,
  household_id    BIGINT NOT NULL,
  owner_id        BIGINT,                            -- NULL = jeder darf
  title           VARCHAR(200) NOT NULL,
  notes           TEXT,
  type            ENUM('flexible','deadline','termin') NOT NULL,
  domain          ENUM('haushalt','privat','business','termin') NOT NULL,
  time_estimate   SMALLINT,                          -- Minuten
  energy          ENUM('niedrig','mittel','hoch'),
  context         ENUM('zuhause','unterwegs','egal') DEFAULT 'egal',
  priority        TINYINT DEFAULT 2,                 -- 1 niedrig .. 3 hoch
  resistance      ENUM('leicht','neutral','ungeliebt') DEFAULT 'neutral', -- KI-geschätzt, rules.md §1
  base_xp         SMALLINT,                          -- KI-bewertet
  recurrence_rule VARCHAR(120),                      -- iCal-RRULE (FREQ=WEEKLY;BYDAY=MO); NULL = einmalig
  due_at          DATETIME,                          -- nur type=deadline
  fixed_at        DATETIME,                          -- nur type=termin
  active          BOOLEAN DEFAULT 1,
  created_by      BIGINT,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_household
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_owner
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_tasks_household (household_id, active, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_occurrences (
  id             BIGINT PRIMARY KEY AUTO_INCREMENT,
  task_id        BIGINT NOT NULL,
  assignee_id    BIGINT,
  scheduled_date DATE,                               -- von KI-Wochenverteilung
  status         ENUM('open','done','skipped','snoozed') DEFAULT 'open',
  snoozed_until  DATETIME,
  awarded_xp     SMALLINT,                           -- tatsächlich vergeben bei done
  completed_at   DATETIME,
  CONSTRAINT fk_occ_task
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_occ_assignee
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_occ_pool (assignee_id, status, scheduled_date)  -- "Was jetzt?"-Abfrage
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  GAMIFICATION  —  Fortschritt, Umschläge
-- ---------------------------------------------------------------------

CREATE TABLE user_progress (
  user_id             BIGINT PRIMARY KEY,
  xp_total            INT DEFAULT 0,
  level               SMALLINT DEFAULT 1,
  sparks              INT DEFAULT 0,
  streak_count        SMALLINT DEFAULT 0,
  streak_last         DATE,
  longest_streak      SMALLINT DEFAULT 0,
  streak_state        ENUM('active','frozen') DEFAULT 'active',  -- rules.md §4 „auf Eis"
  streak_frozen_until DATETIME,                                  -- Eis-Fenster (Familien-Rettung)
  schontag_available  BOOLEAN DEFAULT 0,                         -- Puffer-Tag
  pity_counter        SMALLINT DEFAULT 0,                        -- Lootbox Soft-Pity
  CONSTRAINT fk_progress_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE envelopes (                             -- Glücksumschlag (pochibukuro) -> Sparks
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT NOT NULL,
  source        ENUM('levelup','streak_milestone'),
  sparks_amount INT,
  opened_at     DATETIME,                            -- NULL = noch ungeöffnet
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_env_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_env_unopened (user_id, opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  KOSMETIK  &  THEMEN-LOOTBOXEN  (Gacha, rules.md §3)
-- ---------------------------------------------------------------------

CREATE TABLE cosmetics (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  category    ENUM('tanuki_outfit','frame','app_icon','done_anim','streak_anim','theme'),
  theme       VARCHAR(60),                           -- 'japan','saison','basis' ...
  rarity      ENUM('gewoehnlich','selten','episch','legendaer'),
  name        VARCHAR(120),
  cost_sparks INT,                                   -- optional Direktkauf; NULL = nur via Lootbox
  asset_ref   VARCHAR(255),                          -- Pfad/Key zu Jasons Asset
  meta        JSON,                                  -- z.B. Posen/Mimiken pro Outfit
  INDEX idx_cosmetics_pool (theme, rarity)           -- Lootbox-Ziehung
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lootboxes (                             -- Themen-Box, mit Sparks gekauft
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  theme       VARCHAR(60),
  name        VARCHAR(120),
  cost_sparks INT,
  active      BOOLEAN DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Drop-Rates pro Seltenheit (60/27/10/3 %) + Soft-Pity (Episch+ alle 10)
-- liegen in der App-Config (rules.md §3), nicht in der DB.

CREATE TABLE user_cosmetics (                        -- Inventar
  user_id     BIGINT,
  cosmetic_id BIGINT,
  acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  equipped    BOOLEAN DEFAULT 0,
  PRIMARY KEY (user_id, cosmetic_id),
  CONSTRAINT fk_uc_user      FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_uc_cosmetic  FOREIGN KEY (cosmetic_id) REFERENCES cosmetics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lootbox_openings (                      -- Verlauf (Pity-Audit, Dupe-Refunds)
  id             BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id        BIGINT NOT NULL,
  lootbox_id     BIGINT,
  cosmetic_id    BIGINT,                             -- gezogenes Item
  was_duplicate  BOOLEAN DEFAULT 0,
  sparks_refund  INT DEFAULT 0,                       -- bei Duplikat
  opened_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lbo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_lbo_user (user_id, opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  TANUKI  —  v1: equipped Kosmetik · v2: Reisen/Stats docken hier an
-- ---------------------------------------------------------------------

CREATE TABLE tanuki_profile (
  user_id            BIGINT PRIMARY KEY,
  equipped_outfit_id BIGINT,
  -- RESERVIERT v2 (concept.md §14): attributes JSON, stamina, on_journey_until ...
  CONSTRAINT fk_tanuki_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tanuki_outfit FOREIGN KEY (equipped_outfit_id) REFERENCES cosmetics(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  NUDGES  (v1: Push · v1.1: Telegram/WhatsApp · inkl. Familien-Rettung)
-- ---------------------------------------------------------------------

CREATE TABLE nudges (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT NOT NULL,                     -- Empfänger
  from_user_id  BIGINT,                              -- bei kind='rescue': Absender (Familie)
  occurrence_id BIGINT,
  kind          ENUM('reminder','termin','rescue') DEFAULT 'reminder',
  channel       ENUM('whatsapp','push','telegram'),
  template      VARCHAR(80),
  status        ENUM('scheduled','sent','failed','skipped') DEFAULT 'scheduled',
  scheduled_for DATETIME,
  sent_at       DATETIME,
  CONSTRAINT fk_nudge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_nudge_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_nudge_due (status, scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  Hinweise für die Implementierung (Claude Code):
--   • user_progress + tanuki_profile pro User bei Registrierung anlegen.
--   • Recurrence: RRULE -> task_occurrences per Cron generieren (Woche im Voraus).
--   • "Was jetzt?": Query über idx_occ_pool, dann Scoring (rules.md §5) in PHP/KI.
--   • Lootbox-Ziehung: Seltenheit per Config-Rates, dann Item aus idx_cosmetics_pool.
--   • Konstanten (XP/Level/Drop-Rates/Pity/Eis-Fenster) zentral in einer
--     config.php halten — entspricht der Stellschrauben-Tabelle rules.md §9.
-- =====================================================================
-- Taskly — Migration: Freunde-System + Streak-Rettung
-- (ersetzt das Haushalts-Leaderboard durch ein Freunde-Leaderboard; rules.md §4)
SET NAMES utf8mb4;

-- Freundes-Code pro User (zum Adden)
ALTER TABLE users ADD COLUMN friend_code CHAR(8) UNIQUE AFTER email;

-- Freundschaften (eine Zeile je Beziehung: Anfrager -> Adressat)
CREATE TABLE IF NOT EXISTS friendships (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id    BIGINT NOT NULL,                 -- Anfragender
  friend_id  BIGINT NOT NULL,                 -- Adressat
  status     ENUM('pending','accepted') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pair (user_id, friend_id),
  CONSTRAINT fk_fr_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fr_friend FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_fr_friend (friend_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Streak-Rettungs-Anstupser (Freund stupst eingefrorenen Freund)
CREATE TABLE IF NOT EXISTS rescues (
  id         BIGINT PRIMARY KEY AUTO_INCREMENT,
  to_user    BIGINT NOT NULL,                 -- der/die Gerettete (eingefroren)
  from_user  BIGINT NOT NULL,                 -- der/die Rettende
  seen       TINYINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rs_to   FOREIGN KEY (to_user)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rs_from FOREIGN KEY (from_user) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rs_to (to_user, seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
