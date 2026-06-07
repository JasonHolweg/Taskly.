# Architecture — Taskly

> **Taskly** by Jason Holweg · `taskly.jasonholweg.de` (Hetzner)
> Status: Entwurf v0.1 · baut auf `concept.md` · Stand WhatsApp-Recherche: Juni 2026

---

## 1. Stack

- **Frontend:** PWA, Vanilla JS, HTML/CSS (Design-Tokens aus `design.md`). Mobile-first, Light/Dark.
- **Backend:** PHP 8.x, MySQL 8. Local-first auf dem Hetzner-Server (wie Flora/Glacelia-Stack).
- **KI:** Claude API (Task-Parsing, Wochen-Verteilung, XP, „Was jetzt?"-Auswahl).
- **Speech-to-Text:** separater Provider (Claude nimmt **kein** Audio entgegen — siehe §4.0).
- **Nudges:** WhatsApp Cloud API (direkt, kein BSP) + Web Push (VAPID).
- **Jobs:** Cron / systemd-Timer für Recurrence, Wochen-Verteilung, Nudges, Streak-Rollover.

```
[PWA] ── HTTPS ──> [PHP API] ──> [MySQL]
                      │
                      ├─> Claude API        (parse / plan / select / xp)
                      ├─> STT-Provider      (Sprachnotiz → Text)
                      └─> WhatsApp + WebPush (Nudges, via Cron)
```

## 2. Datenmodell (MySQL)

Kern-Designentscheidung aus `concept.md` §4: **Definition vs. Vorkommen.** Eine Aufgabe ist eine *Definition* (`tasks`); was man erledigt/plant, sind *Vorkommen* (`task_occurrences`). Das löst Wiederholung, Historie und XP-pro-Erledigung sauber.

```sql
-- Familie / Accounts
CREATE TABLE households (
  id           BIGINT PRIMARY KEY AUTO_INCREMENT,
  name         VARCHAR(120),
  invite_code  CHAR(8) UNIQUE,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id              BIGINT PRIMARY KEY AUTO_INCREMENT,
  household_id    BIGINT NOT NULL,
  name            VARCHAR(80),
  email           VARCHAR(190) UNIQUE,
  password_hash   VARCHAR(255),
  whatsapp_phone  VARCHAR(20),          -- E.164, für Nudges
  push_sub        JSON,                 -- Web-Push Subscription
  nudge_prefs     JSON,                 -- z.B. {"channel":"whatsapp","windows":["12:00","18:00"]}
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (household_id) REFERENCES households(id)
);

-- Aufgaben-DEFINITION
CREATE TABLE tasks (
  id               BIGINT PRIMARY KEY AUTO_INCREMENT,
  household_id     BIGINT NOT NULL,
  owner_id         BIGINT,                       -- NULL = jeder darf
  title            VARCHAR(200) NOT NULL,
  notes            TEXT,
  type             ENUM('flexible','deadline','termin') NOT NULL,
  domain           ENUM('haushalt','privat','business','termin') NOT NULL,
  time_estimate    SMALLINT,                     -- Minuten
  energy           ENUM('niedrig','mittel','hoch'),
  context          ENUM('zuhause','unterwegs','egal') DEFAULT 'egal',
  priority         TINYINT DEFAULT 2,            -- 1 niedrig .. 3 hoch
  base_xp          SMALLINT,                     -- KI-bewertet (§4.3)
  recurrence_rule  VARCHAR(120),                 -- iCal-RRULE (z.B. FREQ=WEEKLY;BYDAY=MO); NULL = einmalig
  due_at           DATETIME,                     -- nur type=deadline
  fixed_at         DATETIME,                     -- nur type=termin
  active           BOOLEAN DEFAULT 1,
  created_by       BIGINT,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (household_id) REFERENCES households(id)
);

-- Aufgaben-VORKOMMEN (geplant / erledigt)
CREATE TABLE task_occurrences (
  id             BIGINT PRIMARY KEY AUTO_INCREMENT,
  task_id        BIGINT NOT NULL,
  assignee_id    BIGINT,
  scheduled_date DATE,                            -- von KI-Wochenverteilung gesetzt
  status         ENUM('open','done','skipped','snoozed') DEFAULT 'open',
  snoozed_until  DATETIME,
  awarded_xp     SMALLINT,                        -- tatsächlich vergeben bei done
  completed_at   DATETIME,
  FOREIGN KEY (task_id) REFERENCES tasks(id)
);
-- Index für die "Was jetzt?"-Abfrage:
CREATE INDEX idx_occ_pool ON task_occurrences (assignee_id, status, scheduled_date);

-- Gamification
CREATE TABLE user_progress (
  user_id             BIGINT PRIMARY KEY,
  xp_total            INT DEFAULT 0,
  level               SMALLINT DEFAULT 1,
  sparks              INT DEFAULT 0,
  streak_count        SMALLINT DEFAULT 0,
  streak_last         DATE,
  longest_streak      SMALLINT DEFAULT 0,
  streak_state        ENUM('active','frozen') DEFAULT 'active', -- §4 „auf Eis"
  streak_frozen_until DATETIME,                                 -- Eis-Fenster (Familien-Rettung)
  schontag_available  BOOLEAN DEFAULT 0,                        -- Puffer-Tag
  pity_counter        SMALLINT DEFAULT 0,                       -- Lootbox Soft-Pity
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE envelopes (                          -- Glücksumschlag (Lootbox)
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT NOT NULL,
  source        ENUM('levelup','streak_milestone'),
  sparks_amount INT,
  opened_at     DATETIME,                         -- NULL = noch ungeöffnet
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kosmetik
CREATE TABLE cosmetics (
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  category    ENUM('tanuki_outfit','frame','app_icon','done_anim','streak_anim','theme'),
  theme       VARCHAR(60),                        -- z.B. 'japan','saison','basis'
  rarity      ENUM('gewoehnlich','selten','episch','legendaer'),
  name        VARCHAR(120),
  cost_sparks INT,                                -- optional Direktkauf; NULL = nur via Lootbox
  asset_ref   VARCHAR(255),                       -- Pfad/Key zu Jasons Asset
  meta        JSON                                -- z.B. Posen/Mimiken pro Outfit
);

CREATE TABLE lootboxes (                          -- Themen-Box, mit Sparks gekauft
  id          BIGINT PRIMARY KEY AUTO_INCREMENT,
  theme       VARCHAR(60),
  name        VARCHAR(120),
  cost_sparks INT,
  active      BOOLEAN DEFAULT 1
);
-- Drop-Rates pro Seltenheit + Soft-Pity liegen in der App-Config (rules.md §3), nicht in der DB.

CREATE TABLE user_cosmetics (
  user_id     BIGINT,
  cosmetic_id BIGINT,
  acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  equipped    BOOLEAN DEFAULT 0,
  PRIMARY KEY (user_id, cosmetic_id)
);

-- Tanuki-Anker (v1: nur equipped Kosmetik; v2 dockt hier an — siehe §7)
CREATE TABLE tanuki_profile (
  user_id            BIGINT PRIMARY KEY,
  equipped_outfit_id BIGINT,
  -- RESERVIERT v2: level, attributes JSON, stamina, on_journey_until ...
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Nudge-Log
CREATE TABLE nudges (
  id            BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT,
  occurrence_id BIGINT,
  channel       ENUM('whatsapp','push'),
  template      VARCHAR(80),
  status        ENUM('scheduled','sent','failed','skipped'),
  scheduled_for DATETIME,
  sent_at       DATETIME
);
```

## 3. Aufgaben-Typen → Verhalten

| type | im „Was jetzt?"-Pool | Priorität steigt | Erinnerung |
|---|---|---|---|
| `flexible` | ✅ | nein | optional |
| `deadline` | ✅ | ja, je näher `due_at` | optional |
| `termin` | ❌ | — | ja, X Min vor `fixed_at` |

Die „Was jetzt?"-Abfrage zieht nur `open`-Occurrences von `flexible`/`deadline`-Tasks des Users.

## 4. KI-Pipeline (Claude API)

**Modell-Zuordnung:** Brain-Dump-Parsing (§4.1, inkl. XP-Bewertung) = **Sonnet 4.6** (`claude-sonnet-4-6`), die anspruchsvollste Aufgabe (unstrukturiert → strukturiert). Wochen-Verteilung (§4.2) und „Was jetzt?"-Auswahl (§4.4) = **Haiku 4.5** (`claude-haiku-4-5`) — schnell, günstig, reicht für Auswahl/Sortierung.

### 4.0 Speech-to-Text (Vorstufe)
**Wichtig:** Claude nimmt kein Audio entgegen. Die Sprachnotiz muss zuerst zu Text werden.
**Entscheidung: on-device** — `SpeechRecognition` (Web Speech API) im Browser. Kostenlos, kein Provider.

⚠️ **Risiko früh validieren:** Web-Speech-`SpeechRecognition` ist auf iOS Safari historisch wackelig — teils gar nicht im **Standalone-PWA-Modus** verfügbar, und „on-device" stimmt nur halb (iOS routet die Erkennung oft serverseitig). Da der Sprach-Brain-Dump ein Headline-Feature ist: in einer **installierten iOS-PWA testen, bevor** der Voice-Flow drauf gebaut wird.
- **Sicherheitsnetz:** Text-Eingabe nutzt denselben Parse-Pfad und funktioniert immer. Falls iOS-Voice scheitert, ist serverseitige STT der Fallback nur für die Sprachnotiz — nachrüstbar, ohne den Rest zu ändern.

### 4.1 Brain-Dump → Tasks
Input: Transkript. Output: strukturierte Tasks als striktes JSON (kein Prosa-Preamble).
```json
{ "tasks": [
  { "title":"Bad putzen", "type":"flexible", "domain":"haushalt",
    "time_estimate":30, "energy":"mittel", "context":"zuhause",
    "priority":2, "recurrence_rule":"FREQ=WEEKLY", "base_xp":40 }
]}
```
Tasks werden direkt übernommen (kein Bestätigungs-Gate), bleiben editierbar (`concept.md` §12).

### 4.2 Wochen-Verteilung
Input: offene + wiederkehrende Tasks, Woche, grobe Tagesrhythmus-Präferenzen.
Output: `scheduled_date` je Occurrence. **Die KI plant, nicht der User.**

### 4.3 XP-Bewertung
Default: Teil von 4.1 (KI setzt `base_xp` beim Anlegen). Formel-Leitlinie: Aufwand × Widerstand/Unbeliebtheit × Energie. Konsistenzregeln → `rules.md`.

### 4.4 „Was jetzt?"-Auswahl (das Herz)
Input: Pool offener Occurrences + Live-Kontext (`{zeit:10|30|60, energie:müde|ok|voll, tageszeit, kontext}`) + zuletzt geskippte IDs.
Output: **eine** `occurrence_id` + ein Satz Begründung.
```json
{ "pick": 8412, "reason": "Wenig Akku? Dann was Leichtes — 5 Min." }
```
Regeln (Zeit-Cap, Energie-Match, Anti-Repetition, Tageszeit, Deadline-Nähe) → `rules.md`.

## 5. Nudge-System

Ein Cron-Scheduler (läuft z.B. alle 5 Min, prüft fällige Nudges in `nudges`).

**Auslöser:**
- **Termin-Reminder:** X Min vor `fixed_at`.
- **Idle-/„Was jetzt?"-Nudge:** zu vom User gewählten Fenstern (`nudge_prefs`), wenn offene Tasks existieren. Inhalt: *eine* konkrete Aufgabe + Deep-Link in den „Was jetzt?"-Screen. Nie Listen-Guilt (`brand.md` §5).

### 5.1 v1-Kanal: Web Push (PWA)
- VAPID + Service Worker. iOS unterstützt Web-Push für **installierte** PWAs (ab iOS 16.4), bleibt aber der unzuverlässige Kanal.
- **Ehrliche Einordnung:** das ist die bekannte Schwachstelle. Ohne externen Nachrichten-Kanal lehnt sich der v1-Nudge an iOS-Web-Push + „User öffnet die App". Der ganze „nicht-wegswipebare Nachricht"-Vorteil (das ursprüngliche Kern-Argument) kommt erst mit §5.2.

### 5.2 Externer Nachrichten-Kanal → aufgeschoben (v1.1/v2)
Auf Jasons Entscheidung **nicht in v1** — nachrüstbar, Schema ist vorbereitet (`nudges.channel`, `users.whatsapp_phone`).
- **Telegram Bot API:** kostenlos, keine Verifizierung/Templates, proaktive Nachrichten jederzeit, in Minuten aufgesetzt. Haken: Familie braucht Telegram.
- **WhatsApp Cloud API (Stand Juni 2026):** Abrechnung pro zugestellter Template-Nachricht; proaktive Nudges = Utility-Templates (nicht Marketing → kein ~2/Tag-Cap). 24h-Fenster nach einer User-Nachricht macht Freitext + Utility kostenlos. Familien-Kosten ≈ null; echter Aufwand = Meta-Verifizierung + dedizierte Nummer + Template-Genehmigung (Cloud API direkt, kein BSP-Markup).
- **Hinweis:** von allem Aufgeschobenen ist das das **billigste** (Telegram = paar Stunden) und es behebt die *Kern-Schwäche* aus §5.1 — also eher „v1.1" als ferne Zukunft.

## 6. Auth & PWA

- **Auth:** simpel — Familie tritt per `invite_code` bei, Login E-Mail+Passwort (Argon2id), Server-Sessions/Cookies. Kein OAuth/Public-Signup in v1.
- **PWA:** Manifest (standalone, `theme-color` an Light/Dark), Service Worker (Offline-Shell + Push), Safe-Area-Insets. „Zum Homescreen hinzufügen" ist Pflicht für Push auf iOS.

## 7. v2-Erweiterbarkeit (sperrt §14 nicht zu)

Das v1-Schema ist so gebaut, dass „Tanuki Reisen" (concept §14) **andockt, ohne v1 zu ändern**:
- `tanuki_profile` existiert bereits als Anker → v2 ergänzt Spalten/Tabellen: `attributes JSON`, `stamina`, `on_journey_until`, plus `items`, `user_items`, `journeys`.
- XP/Sparks/Envelopes existieren bereits → die Reise-Belohnungen hängen sich an die **bestehende, aufgaben-getriebene** Ökonomie. Damit bleibt die §14-Regel technisch erzwingbar: Reisen werden aus real verdienten Ressourcen gespeist, nicht aus In-App-Grinden.

## 8. Technische Entscheidungen (gelockt)

1. **Nudge-Kanal:** externer Kanal **aufgeschoben** (§5.2). v1 = nur Web Push. ✅
2. **Speech-to-Text:** **on-device** Web Speech (§4.0) — iOS-Risiko früh validieren, Text-Eingabe als Netz. ✅
3. **Claude-Modelle:** Brain-Dump = **Sonnet 4.6**, Wochen-Verteilung & „Was jetzt?" = **Haiku 4.5**. ✅
4. **Recurrence:** **iCal-RRULE** (kompatibel mit späterem Kalender-Sync, §13). ✅
