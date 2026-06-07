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
