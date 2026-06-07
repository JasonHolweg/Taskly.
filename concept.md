# Concept — Taskly

> **Taskly** by Jason Holweg · ADHS-Aufgaben-App
> Host: `taskly.jasonholweg.de` (Hetzner) · Status: Entwurf v0.2 · folgt: `brand.md`, `architecture.md`, `design.md`, `rules.md`

---

## 1. Problem

Klassische Task-Apps lösen **Tracking**. ADHS scheitert aber nicht am Tracking, sondern an **Initiierung** und **Choice-Paralyse**:

- Motiviert → man will *alles* machen → Overload → man macht *nichts*.
- Die Liste wird zur Vorwurfs-Wand statt zur Hilfe.
- Wöchentliches Planen ist genau die Executive-Function-Arbeit, die ADHS schwer macht — eine App, die *Planen verlangt*, baut sich ihre eigene Hürde.

Es gibt zehntausend Task-Apps. Keine löst „Ich hab grad Bock, sag mir **eine** Sache, die ich jetzt machen kann."

## 2. Kern-Prinzip (Design-Gesetz)

> **Eine Aufgabe. Jetzt. Der kognitive Aufwand des Users geht gegen null.**

Daraus folgt für jede Design-Entscheidung:
- **Die KI plant — nicht der User.** Der User dumpt, die KI strukturiert und verteilt.
- **Nie eine Liste als Antwort.** Auf „Was jetzt?" kommt *genau eine* Aufgabe, nicht zehn.
- **Erinnerung in einem Kanal, den man nicht wegswipet** (Telegram/WhatsApp — v1.1), nicht nur Push.
- Wenn ein Feature den User zum Nachdenken/Planen zwingt → kandidiert es fürs Rauswerfen.

## 3. Zielnutzer (v1)

Jason + Familie. Kleine, vertraute Gruppe. **Speziell mitgedacht: Jasons Mutter** — daraus stammen der Tanuki & die japanischen Brand-Elemente (`brand.md` §9).

Konsequenzen:
- Leichtgewichtige Accounts (Einladung/Familien-Code), **kein** public Signup.
- Family-**Leaderboard** ergibt jetzt Sinn (echte Gegner) → stärkster Motivator.
- Keine Multi-Tenancy-Komplexität, keine Skalierungs-Architektur, keine Monetarisierung in v1.

## 4. Aufgaben-Typen (Datenmodell-Grundlage)

Drei fundamental verschiedene Dinge. Die Trennung ist das Herz des Modells:

| Typ | Hat feste Uhrzeit? | Im „Was jetzt?"-Pool? | Beispiel |
|-----|--------------------|------------------------|----------|
| **Flexible Aufgabe** | nein | **ja** | Bad putzen, Demo-Site deployen, Steuerordner sortieren |
| **Deadline** | nein, aber Fälligkeitsdatum | ja (mit steigender Priorität) | „bis Freitag Rechnung X" |
| **Termin** | ja | **nein** — nur Erinnerung | Zahnarzt 14:00 |

Flexible Aufgaben sind zusätzlich:
- **einmalig** · **wiederholend** (täglich/wöchentlich/custom)

Jede flexible Aufgabe trägt (von KI gesetzt, vom User korrigierbar):
- `zeitaufwand` (Minuten, grob: 5 / 15 / 30 / 60+)
- `energie` (niedrig / mittel / hoch — wie viel „Akku" sie kostet)
- `kontext` (zuhause / unterwegs / egal)
- `prioritaet`
- `domain` (Haushalt / Privat / Business / Termin)
- `xp` (KI-bewertet, siehe §7)
- `wiederholung`

## 5. Die Kern-Loops

**Loop A — Erfassen (Brain-Dump)**
Sprachnotiz: man redet einfach alles runter. KI parst → erzeugt strukturierte Tasks mit allen Feldern oben → Tasks werden **direkt übernommen** (kein Bestätigungs-Gate, minimale Friction). Jede Aufgabe ist nachträglich editierbar, falls die KI etwas falsch verstanden hat. Auch Text-Eingabe möglich.

**Loop B — „Was jetzt?" (das eigentliche Produkt)**
User öffnet App *oder* tippt im WhatsApp-Nudge auf „Was jetzt?". Gibt minimalen Kontext (Default-Annahme: **2 Taps** — Zeit + Energie, siehe offene Frage). KI schlägt **genau eine** Aufgabe vor + ein Satz *warum gerade die*. Aktionen: **Erledigt** · **Skip** (zeig was anderes) · **Snooze**.

**Loop C — Wochen-Verteilung (KI, nicht User)**
KI verteilt wiederkehrende + offene Aufgaben automatisch über die Woche. Der User **plant nicht** — er sieht den Plan und korrigiert höchstens. Sonntag/Wochenstart: kurzer „so sieht deine Woche aus"-Überblick.

**Loop D — Nudge / Erinnerung**
Persistenter Kanal (WhatsApp) + Push. Nicht nur „du hast 8 offene Tasks" (= Vorwurf), sondern „Hast 15 Min? Mach kurz X — gibt 20 XP." Eine konkrete Aufgabe, kein Listen-Guilt.

## 6. Die Auswahl-Logik (das Herz)

Woran die KI die *eine* Aufgabe wählt. Reine Priorität reicht **nicht**:

- **Verfügbare Zeit** — niemals 60-Min-Task vorschlagen, wenn User „10 Min" sagt.
- **Energielevel** — bei niedriger Energie: Quick-Win / niedrige `energie`. Bei hoher: das Dicke, was sonst liegen bleibt.
- **Kontext** — unterwegs ≠ zuhause-Tasks.
- **Tageszeit** — Laute/Nachbar-relevante Tasks nicht um 22:00.
- **Deadline-Nähe** — Priorität steigt, je näher das Fälligkeitsdatum.
- **Anti-Repetition** — nicht 3× hintereinander dasselbe vorschlagen; Skip merken.

→ Genau hier verdient die KI ihren Platz. Ohne diese Dimensionen ist es ein Random-Picker mit Extraschritten.

## 7. Wo die KI sitzt (Claude API)

1. **Sprache → Task** — Strukturierung des Brain-Dumps in Felder.
2. **Wochen-Verteilung** — Loop C.
3. **XP-Bewertung** — wie viel XP eine Aufgabe gibt (Aufwand × Unbeliebtheit/Widerstand × Energie). Konsistent, nicht inflationär.
4. **„Was jetzt?"-Auswahl + Begründung** — Loop B.

## 8. Gamification (volle Schleife in v1)

Komplette Belohnungs-Ökonomie, **rein kosmetisch** — alles wird *erarbeitet*, nichts mit Echtgeld gekauft.

**Progression:**
- **XP** pro erledigter Aufgabe (KI-bewertet, siehe §7)
- **Level** (XP-Schwellen) → Level-Up gibt einen **Glücksumschlag** (siehe Ökonomie)
- **Streaks** (Tage in Folge mit ≥1 erledigter Aufgabe); Meilensteine (z. B. 7 Tage) geben einen Umschlag
- **Family-Leaderboard** (gegen Familie)

**Ökonomie (zwei Stufen):**
- **Glücksumschlag** (pochibukuro) aus Level-Up/Streak → öffnet → **Sparks** (Währung). Erster Dopamin-Kick.
- **Sparks → Themen-Lootboxen** (z. B. „Japan", „Saison") → öffnen → zufällige **Kosmetik nach Seltenheit** (Gewöhnlich → Selten → Episch → Legendär). Zweiter, größerer Kick.
- **Kosmetik-Kategorien (alle aus Boxen):** Tanuki-Garderobe (Outfits mit Posen/Mimiken, Hauptkategorie), Profilrahmen, App-Icons, Erledigt-/Streak-Animationen, Themes.
- Duplikate → Spark-Rückerstattung; Soft-Pity gegen Pech. Optional: einzelne Signature-Items zusätzlich direkt kaufbar. Details & Zahlen → `rules.md` §3.

**Guardrails (damit das Meta-Game nicht das echte Spiel frisst):**
1. **Kosmetik gated nie Funktion** — kein Item ändert, *welche* Aufgaben man sieht/kann.
2. **Sparks gibt's nur fürs Erledigen** (+ Streak-Meilensteine), nie fürs reine App-Öffnen oder Warten.
3. **Kein Echtgeld** in v1 — reine Verdien-Ökonomie.
4. XP/Spark-Vergabe **konsistent statt inflationär** (Regeln in `rules.md`).

## 9. Erinnerungs-Kanäle

- **v1: Push (PWA)** — schnell, aber auf iOS unzuverlässig + wird weggeswipet. Bekannte Schwachstelle.
- **v1.1/v2 (aufgeschoben): externer Nachrichten-Kanal** (Telegram oder WhatsApp) — der „nicht-ignorierbare" Kanal, der die Push-Schwäche behebt. Schema vorbereitet, bewusst nicht in v1. Details + Kostenlage → `architecture.md` §5.2.

## 10. Tech-Stack (Kurzfassung — Detail in `architecture.md`)

- PWA (Vanilla JS), PHP, MySQL, Claude API, WhatsApp Cloud API.
- Backend-Cron für Nudges + Wochen-Verteilung.
- Native/Capacitor-Wrapper: bewusst aufgeschoben; WhatsApp als Nudge-Kanal macht reine PWA tragfähig.

## 11. Was NICHT in v1 (Non-Goals)

- ❌ Public Signup / fremde Nutzer
- ❌ Echtgeld / In-App-Käufe (Ökonomie ist rein verdient)
- ❌ Native App (PWA reicht, WhatsApp trägt die Nudges)
- ❌ Externe Kalender-Sync → **Backlog v2** (§13)
- ❌ Komplexe Wochen-Planungs-UI für den User (die KI plant)

---

## 12. Getroffene Entscheidungen (gelockt)

1. **„Was jetzt?"-Friction:** 1 Tap + 2 Quick-Buttons (Zeit 10/30/60 · Energie müde/ok/voll). Kein Formular. ✅
2. **Gamification v1:** volle Schleife inkl. Coins + Lootkisten, **rein kosmetisch** (§8). ✅
3. **Energie:** 3 Stufen (müde / ok / voll). ✅
4. **Externe Kalender:** später → Backlog (§13). ✅
5. **Brain-Dump:** KI-Output wird direkt übernommen, nachträglich editierbar. ✅
6. **Name:** **Taskly by Jason Holweg**, Host `taskly.jasonholweg.de` (Hetzner). ✅

## 13. Backlog (v2+)

- **Externe Kalender-Sync** (Google/Apple) für Termine — laut Jason hoher Wert für ihn + seinen Vater (Unternehmer, viele Termine).
- Spark-**Themes & Sounds** (inkl. japanische/saisonale Themes) als weitere Kosmetik-Kategorien.
- Lootkisten/Sparks-**Echtgeld**? Bewusst NICHT v1; später evaluieren.
- Native/Capacitor-Wrapper, falls PWA-Limits drücken.

## 14. Vision (v2+) — „Tanuki Reisen" (Idle-RPG-lite)

Inspiriert von Shakes & Fidget: Tanuki bekommt Attribute, geht auf **Reisen**, findet Items, wird stärker. Reisen brauchen Zeit (Idle-Timer) und Ausdauer.

**Warum das funktioniert (die starke Idee):** Nicht das RPG ist der Clou, sondern der **Idle-Timer**. „Tanuki ist 30 Min unterwegs" = genau das Zeitfenster, in dem der User eine echte Aufgabe macht. Das Spiel *zeigt auf die echte Aufgabe*, statt mit ihr zu konkurrieren.

**Die Regel, die es vor der Meta-Game-/Komplexitäts-Falle schützt:**
> Das RPG wird **ausschließlich von echten Aufgaben angetrieben**. Tanuki wird stärker, weil der User Tasks erledigt hat (XP/Sparks/Items als Belohnung *für reale Arbeit*) — **nie** durch In-App-Grinden oder ein Minispiel. Sobald man im Spiel vorankommt, ohne real etwas zu tun, ist der Loop kaputt.

**Bedingung an v1:** Datenmodell + Spark-Ökonomie so bauen, dass Tanuki-Stats, Items und Reisen *später andockbar* sind, ohne dass v1 sie enthält. Kein RPG in v1 — aber kein Schema, das es verbaut. (→ `architecture.md`)
