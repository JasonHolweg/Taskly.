# Taskly — Handover & Kickoff (für Claude Code)

> **Taskly** by Jason Holweg · `taskly.jasonholweg.de` (Hetzner)
> Lies dieses File zuerst, dann die referenzierten Docs. Sie sind die Quelle der Wahrheit — nicht neu herleiten, sondern befolgen.

## Was ist Taskly
Eine ADHS-Aufgaben-App, die **Initiierung & Choice-Paralyse** löst, nicht Tracking. Kernversprechen: statt einer To-do-Wand sagt sie dir **eine Sache, die du jetzt machen kannst** — und belohnt dich dafür. Für Jason + Familie (speziell seine Mutter). PWA, iOS-first.

## Quelle der Wahrheit (in diesem Ordner)
| Datei | Inhalt |
|---|---|
| `concept.md` | Vision, Loops, Aufgaben-Typen, Gamification, v2-Vision §14 |
| `brand.md` | Name, Stimme, Tanuki-Maskottchen, Sparks |
| `design.md` | Design-Tokens (CSS-Variablen, Light/Dark, Indigo) — **1:1 übernehmen** |
| `taskly_style_tile.html` | Der gerenderte Look als Referenz |
| `architecture.md` | Stack, KI-Pipeline, Nudges |
| `rules.md` | XP/Level/Sparks/Lootbox/Streak-Logik + „Was jetzt?"-Scoring + Copy |
| `schema.sql` | Komplettes MySQL-Schema, einspielbereit |

## Nicht-verhandelbare Prinzipien (sonst ist es das falsche Produkt)
1. **Eine Aufgabe auf „Was jetzt?" — niemals eine Liste.**
2. **Die KI plant, nicht der User.** Kein Wochen-Planungs-Zwang.
3. **Kein Schuld-UI:** keine roten Overdue-Badges, keine „X überfällig"-Zähler.
4. **Skip & Snooze immer straffrei.** Snooze verschiebt, löscht nie.
5. **XP/Sparks nur bei echter Erledigung.**
6. **Streak-Bruch tröstet, bestraft nie** (Ton: `brand.md` §5, Logik: `rules.md` §4).

## Stack & Konventionen
- **PHP 8.x · MySQL 8 · Vanilla JS** (kein Framework). PWA (Manifest + Service Worker).
- **Design:** CSS-Variablen-Block aus `design.md` als globales `:root` + `[data-theme="dark"]`.
- **KI-Modelle:** Brain-Dump-Parsing = `claude-sonnet-4-6`; Wochen-Verteilung & „Was jetzt?"-Auswahl = `claude-haiku-4-5`.
- **Alle Stellschrauben** (XP-Kurve, Drop-Rates, Pity, Eis-Fenster …) zentral in **`config.php`** — Werte aus `rules.md` §9. Nicht im Code verstreuen.
- Sprache der UI: Deutsch.

## v1-Scope
**Drin:** Brain-Dump (Text + Voice-Versuch), Aufgaben (flexible/deadline/termin), KI-Wochenverteilung, „Was jetzt?", XP/Level/Streak, Glücksumschlag → Sparks, Themen-Lootboxen mit Seltenheiten, Tanuki-Garderobe, Family-Leaderboard, Web-Push.
**Non-Goals (NICHT bauen):** Echtgeld/In-App-Käufe · Public Signup · Native App · externer Nachrichten-Kanal (Telegram/WhatsApp = v1.1) · Kalender-Sync · „Tanuki Reisen"-RPG (concept §14, v2). Schema lässt diese andocken — v1 nutzt sie nicht.

## Bekanntes Risiko (früh prüfen)
On-device Web-Speech (`SpeechRecognition`) ist auf iOS Safari/PWA wackelig (`architecture.md` §4.0). **Daher Text-Eingabe zuerst bauen** (gleicher Parse-Pfad), Voice als Progressive Enhancement. Falls iOS-Voice scheitert: serverseitige STT nachrüstbar, ohne den Rest zu ändern.

---

## Erster Auftrag: der Kern-Loop
Baue *nur* dies, bevor Gamification-Tiefe drankommt:

1. **Setup:** `schema.sql` einspielen; `config.php` mit den `rules.md`-§9-Defaults; Projektstruktur anlegen.
2. **Erfassen (Loop A):** Eingabe (Text; Voice optional) → `claude-sonnet-4-6` parst zu strukturierten Tasks (JSON, Schema in `architecture.md` §4.1) → in `tasks`/`task_occurrences` speichern → ohne Bestätigungs-Gate, aber editierbar.
3. **„Was jetzt?" (Loop B):** Button → 2 Quick-Selects (Zeit 10/30/60 · Energie müde/ok/voll) → harte Filter + Scoring (`rules.md` §5) ggf. via `claude-haiku-4-5` → zeigt **genau eine** Aufgabe + ein Satz Begründung → Aktionen Erledigt / Skip / Snooze.
4. **Belohnung:** „Erledigt" vergibt XP (`rules.md` §1), aktualisiert `user_progress`, kleiner Pop (kein Listen-Update als Antwort).

**Akzeptanzkriterien:**
- „Was jetzt?" liefert nie mehr als eine Aufgabe.
- Zeit-Filter respektiert (kein 60-Min-Task bei „10 Min").
- Skip zeigt sofort die nächstbeste, straffrei.
- UI nutzt die `design.md`-Tokens, Light + Dark.
- Erledigen erhöht XP sichtbar; kein Schuld-UI nirgends.

Gamification-Tiefe (Lootboxen, Garderobe, Leaderboard, Streak-Eis) und Wochenverteilung als **zweiter** Schritt, sobald der Kern-Loop trägt.
