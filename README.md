# Taskly — Handover & Stand (für Claude Code)

> **Taskly** by Jason Holweg · **live: https://taskly.jasonholweg.de** (Hetzner)
> ADHS-Aufgaben-App, PWA, Deutsch, iOS-first. Diese Datei = aktueller Projektstand.
> **Detail-Infos zum Live-Deployment** stehen im Auto-Memory (`memory/taskly-deployment.md`,
> lädt in neuer Session automatisch). Produkt-Quelle der Wahrheit: die Docs unten.

## Was ist Taskly
Löst **Initiierung & Choice-Paralyse**, nicht Tracking. Statt To-do-Wand sagt sie dir
**eine Sache, die du jetzt machen kannst** — und belohnt dich. Für Jason + Familie (Mutter Marlis).

## Nicht-verhandelbare Prinzipien
1. **Eine Aufgabe auf „Was jetzt?" — niemals eine Liste.**
2. **Die KI plant, nicht der User.**
3. **Kein Schuld-UI** (keine roten Overdue-Badges/Zähler).
4. **Skip & Snooze immer straffrei.** Snooze verschiebt, löscht nie.
5. **XP/Sparks nur bei echter Erledigung.**
6. **Streak-Bruch tröstet, bestraft nie.**

## Produkt-Docs (Quelle der Wahrheit)
`concept.md` (Vision/Loops/v2) · `brand.md` (Stimme/Tanuki/Sparks) · `design.md` (Tokens, 1:1) ·
`architecture.md` (Stack/KI/Nudges) · `rules.md` (XP/Lootbox/Streak/Scoring §9 = alle Stellschrauben) ·
`schema.sql` (Basis-Schema) + `db/migrations/*` (spätere Änderungen).

---

## ✅ Status: v1 komplett + viele Erweiterungen — alles live
- **Kern-Loop:** Brain-Dump (Text + Voice) → Sonnet parst → „Was jetzt?" (Filter+Scoring, **KI-Begründung** via Haiku) → Erledigt/Skip/Snooze. Eine Aufgabe, kein Schuld-UI.
- **Aufgaben:** flexible/deadline/termin, **bearbeiten & löschen** (Plan-Ansicht).
- **Wochen-Verteilung:** Haiku verteilt über die Woche (rules §6) + Recurrence (RRULE) + **Crons** (Mo 04:00 Plan, tgl. 03:00 Streak, alle 5 Min Nudges).
- **Gamification:** XP/Level/Streak · **Streak-Eis + Freundes-Rettung** · Glücksumschlag (Pochibukuro) → Sparks · **1–5 Sparks pro Erledigung**.
- **Kosmetik:** Tanuki-Garderobe (Outfits), **44 Frames / 15 Kategorien**, Themen-Lootboxen (No-Dupe-Sammelmodell, Outfits **und** Frames droppen zusammen).
- **Tanuki-Hub** (Tab „Tanuki", ex-Shop): großer ausgerüsteter Tanuki im Rahmen + Kategorie-Buttons (Garderobe/Rahmen/Animationen/App-Icons/Items/Shop). Animationen/Icons/Items = Platzhalter (für v2 „Tanuki Reisen" vorbereitet).
- **Sozial:** Freunde per Code, **Freunde-Leaderboard** (ersetzt Familie).
- **Web-Push** (VAPID): Termin-Reminder, „Was jetzt?"-Nudges zu Zeitfenstern, Rettungs-Push.
- **Kalender-Sync** (Export): abonnierbarer iCal-Feed `/cal/<token>.ics`.
- **Konto:** Name/E-Mail/Passwort ändern.
- **PWA · Dark Mode** (folgt System) · **Onboarding** · **Heute = max. einfach** (Tageszeit-Gruß, sofort klickbarer CTA, Mikro-Button für Kopf-leeren, Zeit/Akku hinter „Anpassen").

## Stack & Infrastruktur
- **Server:** `root@178.104.95.146` (Ubuntu 24.04, Apache 2.4, **PHP 8.3**, MySQL 8). Viele andere Sites laufen hier — vorsichtig.
- **Webroot:** `/var/www/taskly.jasonholweg.de/` (Git-Clone). DocumentRoot → `public/`. Apache-vHosts + Let's-Encrypt (Certbot).
- **DB:** `taskly` / User `taskly@localhost`. Zugang in `/root/.taskly-db.cnf` (chmod 600).
- **Secrets:** `src/config.php` (gitignored, nur auf Server) — DB-Pass, **Anthropic-API-Key** (gesetzt, Sonnet+Haiku aktiv; bei Ausfall greift Heuristik-Fallback), VAPID-Keys. Vorlage: `src/config.example.php`. Alle Tuning-Werte unter `'tuning'` (rules §9).
- **Composer:** `minishlink/web-push` (für Push). `vendor/` server-only (gitignored), `composer.json/lock` im Repo.

## Code-Struktur
```
src/              (außerhalb Webroot)
  config.php          secrets+tuning (server-only)  · config.example.php
  bootstrap.php       lädt config/db/libs, Session (CLI-safe)
  db.php              PDO
  lib/  helpers, gamification (XP/Level/Streak), selection („Was jetzt?"-Scoring),
        claude (Sonnet-Parse + Haiku smart_reason), recurrence (RRULE), planner (Wochenverteilung),
        cosmetics (Outfit/Frame-Resolver), gacha (Lootbox no-dupe), social (Freunde/Leaderboard/Rettung),
        push (Web-Push), calendar (iCal)
public/             (= DocumentRoot)
  index.html  ·  assets/css/styles.css  ·  assets/js/app.js  ·  sw.js  ·  manifest.webmanifest
  assets/img/tanuki/ (Outfit-Posen + base-emotions + Icons, server-only/gitignored)
  assets/img/pochibukuro/ (Lootbox-/Umschlag-Art, server-only/gitignored)
  api/  state, braindump, whatnow, skip, snooze, complete, tasks, week, plan_week,
        shop, openbox, equip, buy, friends, leaderboard, rescue, push, calendar, account, auth/*
bin/  cron_plan_week.php · cron_daily.php · cron_nudges.php
db/   schema.sql · seed_*.sql · migrations/*
```
Frontend ist **Vanilla JS** (kein React!). UI über `data-`-Attribute + CSS-Tokens. Single-Page mit
Views (Heute/Plan/Tanuki/Mehr) per `showView()`.

## Wichtige Workflows
- **Deployen:** lokal committen + `git push`; dann auf Server `cd /var/www/taskly.jasonholweg.de && git pull --ff-only && chown -R www-data:www-data .`. Bei jeder Frontend-Änderung **`CACHE` in `public/sw.js` hochzählen** (aktuell `taskly-v24`), sonst sehen Clients altes JS/CSS.
- **Bilder (server-only):** Raw-PNGs in `tanuki-raw/` bzw. `pochibukuro-raw/` (Originale, gitignored; „Bereits gecheckt"-Unterordner = schon verarbeitet). Mit PIL-Skript skalieren/umbenennen → `public/assets/img/...` → per `rsync` hochladen + chown. Bilder sind **gitignored** (nicht im Repo).
- **Frame hinzufügen:** 1 CSS-Zeile in `styles.css` (`.hero[data-frame="slug"]{--f-grad:…;--f-glow:…;--f-corner:…}`) + 1 `cosmetics`-Zeile (category `frame`, theme, asset_ref=slug). Eck-Ornamente als SVG-Data-URI in `:root`.
- **Outfit/Box hinzufügen:** Bilder verarbeiten → `cosmetics`(tanuki_outfit, meta.poses) bzw. `lootboxes` seeden. Box zieht Outfits **und** Frames des Themes (no-dupe).
- **DB-Migration:** `db/migrations/*.sql` schreiben, `mysql taskly < datei.sql` auf Server, in `schema.sql` dokumentieren.
- **Testen:** Wegwerf-Accounts `…@example.com` per API anlegen, am Ende per ID löschen.
- **Visuelle QA:** kein Live-Login im Preview möglich (Preview-Browser nur localhost; PHP lokal nicht verfügbar). → **statische Mock-HTML** mit echten CSS-Klassen bauen + `.claude/launch.json` „taskly-node" (Node-Static-Server, ABSOLUTER Pfad — Python http.server ist sandbox-blockiert) + Preview-MCP-Screenshot. `frames-preview.html` zeigt alle Frames.

## Offene Punkte / nächste Schritte
- **iOS-Voice** in installierter PWA real validieren/härten (größtes Risiko, architecture §4.0).
- **Animationen / App-Icons** echt befüllen (UI-Platzhalter im Tanuki-Hub stehen).
- **Items/Slots** → „Tanuki Reisen v2" (concept §14): tanuki_profile hat reservierte v2-Spalten.
- **Lootbox-Art fehlt** für Frame-Kategorien royal/arcane/winter/halloween (sind aktuell Direktkauf).
- **Kalender-Import** (Termine lesen → drumherum planen) = OAuth/CalDAV, größerer Schritt.
- **Telegram-Nudges** (v1.1): robuster als iOS-Web-Push.
- **Security-Review** vor weiterem Nutzerwachstum.

## Wichtige Hinweise
- **Echte Accounts auf der Live-DB** (NIE als Testdaten anfassen/löschen): u.a. `hallo@jasonholweg.de` (Jason), `mhsmyla2000@gmail.com` (Marlis, Mutter), weitere reale Registrierungen. Nur `@example.com` ist Test.
- **Anthropic-API-Key** wurde einmal im Chat geteilt — bei Bedarf rotieren.
- Kosmetik-Slugs `asset_ref` müssen mit den Bilddateinamen bzw. CSS-`data-frame`-Werten exakt übereinstimmen.
