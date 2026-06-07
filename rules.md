# Rules — Taskly

> **Taskly** by Jason Holweg · das „Gehirn" der App
> Status: Entwurf v0.1 · baut auf `concept.md`, `brand.md`, `architecture.md`
> Zahlen sind **Defaults zum Tunen** (🔧). Alle gesammelt in §9.

---

## 1. XP-Vergabe

XP wird bei der Erledigung einer Occurrence vergeben (`awarded_xp`). `base_xp` setzt die KI beim Brain-Dump-Parsing (Sonnet 4.6).

**Leitformel:** `XP = Zeit-Basis × Widerstands-Faktor`

| Zeitaufwand | Zeit-Basis 🔧 |
|---|---|
| ≤ 5 Min | 10 |
| ~15 Min | 20 |
| ~30 Min | 40 |
| 60+ Min | 60 |

**Widerstands-Faktor** (das ADHS-relevante Stück — was man *aufschiebt*, soll mehr geben):
| Widerstand | Faktor 🔧 |
|---|---|
| leicht / mag man | ×1.0 |
| neutral | ×1.2 |
| ungeliebt / wird aufgeschoben | ×1.5 |

> Beispiel: „Steuerordner sortieren" (30 Min, ungeliebt) → 40 × 1.5 = **60 XP**. „Pflanzen gießen" (5 Min, leicht) → **10 XP**.

**Regeln:**
- XP gibt's **nur bei echter Erledigung**. Nie fürs Öffnen, Planen oder Snoozen.
- Skip & Snooze = **0 XP, 0 Strafe**.
- **Widerstands-Faktor schätzt die KI selbst** beim Brain-Dump-Parsing (aus Ton & Task-Art); beim Editieren überschreibbar.
- Termine geben kein XP (sind nur Erinnerung, keine flexible Leistung).
- XP-Werte werden gerundet auf 5er.

## 2. Level-Kurve

Früh schnell (sofortiges Erfolgserlebnis), dann langsamer.

**Formel 🔧:** XP für `L → L+1` = `100 + 50 × (L-1)`

| Level | XP für nächstes | Kumulativ |
|---|---|---|
| 1→2 | 100 | 100 |
| 2→3 | 150 | 250 |
| 3→4 | 200 | 450 |
| 4→5 | 250 | 700 |
| 5→6 | 300 | 1.000 |
| 6→7 | 350 | 1.350 |
| 7→8 | 400 | 1.750 |

Ab L10: gleiche Formel weiterlaufen lassen oder auf ×1.1-Wachstum umstellen (tunen, wenn Daten da sind). Ein aktiver Tag ≈ 3–5 Tasks ≈ **80–150 XP**, also ~Level alle 1–2 Tage am Anfang.

**Jedes Level-Up → 1 Glücksumschlag** (§3).

## 3. Sparks, Umschläge & Themen-Lootboxen

Zwei Stufen, klar getrennt:

**Stufe 1 — Glücksumschlag → Sparks.** Level-Ups & Streak-Meilensteine geben einen Umschlag (pochibukuro), der sich öffnet → variabler Spark-Betrag (erster kleiner Dopamin-Kick).

| Quelle | Sparks 🔧 |
|---|---|
| Level-Up-Umschlag | 20–60 (zufällig) |
| Streak 3 / 7 / 14 / 30 Tage | 20 / 50 / 120 / 300 |

**Stufe 2 — Sparks → Themen-Lootbox → Kosmetik.** Man kauft mit Sparks **keine Einzel-Items**, sondern **Themen-Boxen** (z. B. „Japan", „Saison", „Basis"). Eine Box enthält Items *eines Themas* quer über alle Kategorien (Outfits, Rahmen, Icons, Animationen, Themes) — verteilt nach **Seltenheit**:

| Seltenheit | Drop-Rate 🔧 |
|---|---|
| Gewöhnlich | 60 % |
| Selten | 27 % |
| Episch | 10 % |
| Legendär | 3 % |

**Öffnen:** zieht zuerst die Seltenheit, dann ein zufälliges Item dieses Themas + Seltenheit. Das ist der zweite, größere Dopamin-Kick (das Lootbox-Gefühl).

**Fairness (damit RNG nicht frustriert) 🔧:**
- **Duplikat → Spark-Rückerstattung** (Gewöhnlich 10 / Selten 25 / Episch 60 / Legendär 150) → fließt zurück in neue Boxen.
- **Soft-Pity:** garantiert **Episch+** spätestens alle 10 Boxen ohne eine.
- **Optional:** einzelne Signature-Items zusätzlich direkt kaufbar (teuer), damit ein gezielter Wunsch erfüllbar bleibt (`cosmetics.cost_sparks`).

**Box-Preis 🔧:** ~120 Sparks/Box.

> **Kalibrierung (dein Wunsch: 1 Woche):** ~1 Woche aktiv ≈ ~250 Sparks ≈ **~2 Boxen** → mehrere Items inkl. realistischer Episch-Chance. Häufige kleine Treffer, seltene große. **Nie Echtgeld.**

## 4. Streaks (ADHS-human, mit Familien-Rettung)

Vierstufig, von sanft zu endgültig:

1. **+1** pro Kalendertag mit ≥ 1 erledigter Occurrence.
2. **Schontag 🔧:** bei Streak ≥ 3 ein **Puffer-Tag**. Ein verpasster Tag verbraucht ihn lautlos, Streak läuft weiter. Puffer lädt nach **7 zusammenhängenden Tagen** nach.
3. **„Auf Eis" 🔧 (Duolingo-Style):** ist der Puffer weg und ein Tag fällt aus, **bricht die Streak nicht sofort** — sie friert ein (`streak_state='frozen'`) für ein Rettungsfenster (Default **48 h**). Eingefroren = sichtbar, aber pausiert.
4. **Familien-Rettung (der warme Hook):** im Eis-Fenster kann ein Familienmitglied eine **Rettungs-Erinnerung** schicken. Erledigt der User dann **eine** Aufgabe, **taut die Streak auf** und läuft weiter. → Familie hält sich gegenseitig am Laufen (passt zur App-für-Mama-Idee).
5. **Bruch:** erst wenn das Eis-Fenster ungenutzt verstreicht. **Reset auf 0**, Ton tröstend (§7), `longest_streak` bleibt als „du kannst das".

- **Kein** Druck-UI: keine bedrohlichen Countdowns. Der Tanuki reagiert, das Zahlenwerk bleibt leise.

> Begründung: Ein schlechter Tag darf keine 20-Tage-Streak atomisieren — und die Rettung durch Familie macht aus dem Verlust-Moment einen **Verbindungs-Moment** statt Scham.

## 5. „Was jetzt?"-Auswahl (das Herz)

Läuft auf **Haiku 4.5**. Input: Pool offener Occurrences + Live-Kontext + jüngste Skips. Output: **eine** ID + ein Satz.

### 5.1 Harte Filter (müssen erfüllt sein)
1. `status = open`, fällig heute / überfällig / ungeplanter Pool, Typ `flexible`|`deadline`.
2. `time_estimate ≤ verfügbare_Zeit × 1.2` (kleine Toleranz).
3. Kontext passt (`unterwegs` → keine `zuhause`-only Tasks).
4. nicht in der Skip-Liste dieser Session.
5. nicht `snoozed` (außer `snoozed_until` < jetzt).

### 5.2 Scoring (Rangliste der Überlebenden)
```
score =  w1 * energie_match      // |task.energy − user.energy| klein = hoch
       + w2 * deadline_naehe      // je näher due_at, desto höher (eskalierend)
       + w3 * prioritaet
       + w4 * tageszeit_eignung   // laut nicht spät, draußen bei Tag …
       − w5 * wiederholungs_malus // kürzlich vorgeschlagen/geskippt
       + w6 * quick_win_bonus     // nur wenn user.energy = müde → kurze Tasks
```
Default-Gewichte 🔧: `w1=3, w2=4, w3=2, w4=2, w5=3, w6=2`.

### 5.3 Verhalten
- **Energie zuerst:** müde → bewusst Quick-Win, um Momentum zu bauen („eine leichte Sache erledigt" schlägt „nichts, weil überfordert").
- **Tie-Break:** näheste Deadline, dann älteste offene Occurrence.
- **Skip:** merkt die ID für diese Session, schlägt direkt die nächstbeste vor. Unbegrenzt, ohne Strafe.
- **Leerer Pool nach Filtern:** ehrlich sagen „Für 10 Min & müde hab ich grad nichts Passendes — Pause ist auch ok." (kein erzwungener Vorschlag).

## 6. Wochen-Verteilung

Läuft auf **Haiku 4.5**, generiert `task_occurrences` aus RRULE für die Woche.

**Regeln 🔧:**
- Max **4 flexible Tasks/Tag** als „geplant" markieren (Vorschlag, **keine** Pflicht — „Was jetzt?" zieht trotzdem aus dem ganzen Pool).
- Schwere Tasks (60+ Min / hoch-Energie) **nicht stapeln** — max 1/Tag.
- Domains über die Woche **mischen**, nicht alle Haushalts-Tasks auf einen Tag.
- Fixe Termine respektieren, drumherum planen.
- Mindestens **1 leichter Tag/Woche** einplanen (Erholung ist Teil des Systems).
- Überfälliges sanft nach vorne ziehen, nie als „Schuldenberg" darstellen.

## 7. Copy-Patterns (Stimme aus `brand.md` §5)

Kurz, du-Form, Tanuki-warm, nie nörgelnd. Jeweils mehrere Varianten rotieren (gegen Abnutzung).

**Nudge**
- „Hast 15 Min? {task} — gibt {xp} XP. 💪"
- „Kleine Sache gefällig? {task} wartet, dauert nur {min} Min."

**„Was jetzt?"-Begründung**
- müde: „Wenig Akku? Dann was Leichtes: {task}."
- voll: „Du hast Energie — pack das Dickere an: {task}."
- Deadline: „{task} wird langsam dringend — guter Moment."

**Erledigt**
- „Erledigt. +{xp} XP. Eine weniger im Kopf. ✨"
- „Stark. Der Tanuki freut sich. +{xp} XP."

**Level-Up / Umschlag**
- „Level {n}! 🎉 Ein Glücksumschlag wartet auf dich."
- „Aufgemacht: +{sparks} Sparks. Zeit für ein neues Outfit?"

**Streak-Bruch** *(markenkritisch — trösten, nie strafen)*
- „Streak ist weg — passiert, war eine starke Serie. Heute eine Sache, und wir starten neu."
- „Kein Drama. Dein Rekord steht bei {longest} Tagen. Auf geht's, Schritt eins."

**Leerzustand**
- „Nichts dringend. Genieß die Pause. 🍵"

> Platzhalter `{…}` füllt das Backend. Variantenpool bewusst klein halten, aber rotieren.

## 8. System-Guardrails (gegen Überforderung)

1. **Eine Aufgabe** auf „Was jetzt?" — niemals eine Liste.
2. **Home zeigt minimal:** heutiger Vorschlag + dezenter Fortschritt. Das volle Backlog ist *nicht* der Startbildschirm.
3. **Kein Schuld-UI:** keine roten Overdue-Badges, keine Zähler offener Aufgaben als Vorwurf.
4. **Skip & Snooze immer straffrei.** Snooze verschiebt, löscht nie.
5. **Sparks/XP nur bei echter Erledigung** (verankert auch die §14-Vision-Regel).
6. **Belohnungs-Inflation vermeiden:** nicht jede Mini-Aktion bekommt Konfetti — sonst wird nichts mehr wertvoll.

## 9. Stellschrauben (alle Defaults an einem Ort) 🔧

| Parameter | Default |
|---|---|
| Zeit-Basis XP (5/15/30/60+ Min) | 10 / 20 / 40 / 60 |
| Widerstands-Faktor (leicht/neutral/ungeliebt) | 1.0 / 1.2 / 1.5 |
| Level-Formel (L→L+1) | 100 + 50×(L-1) |
| Level-Up-Umschlag | 20–60 Sparks |
| Streak-Boni (3/7/14/30) | 20 / 50 / 120 / 300 |
| Box-Preis | ~120 Sparks |
| Drop-Rates (Gew/Selt/Epi/Leg) | 60 / 27 / 10 / 3 % |
| Dupe-Rückerstattung (Gew/Selt/Epi/Leg) | 10 / 25 / 60 / 150 |
| Soft-Pity (Episch+ garantiert) | alle 10 Boxen |
| Schontag-Puffer / Nachladen | 1 Tag / nach 7 Tagen |
| Streak-Eis-Fenster | 48 h |
| Selection-Gewichte w1–w6 | 3 / 4 / 2 / 2 / 3 / 2 |
| Max geplante flexible Tasks/Tag | 4 |
| Zeit-Filter-Toleranz | ×1.2 |

---

## Getroffene Entscheidungen & eine neue Frage

Gelockt:
1. **Spark-Balance:** ~1 Woche aktiv ≈ ein guter Treffer (§3). ✅
2. **Kosmetik = Gacha:** Themen-Lootboxen mit Seltenheiten (Gewöhnlich/Selten/Episch/Legendär) statt Direkt-Shop. ✅
3. **Streak:** Puffer → „auf Eis" → Familien-Rettung → Bruch (§4). ✅
4. **Widerstand:** KI schätzt selbst (§1). ✅

Neu, dein Call:
5. **Signature-Direktkauf:** Sollen ein paar Wunsch-Items (z. B. Legendär-Outfits) *zusätzlich* direkt teuer kaufbar sein — als Frust-Ventil gegen reines RNG (wichtig für weniger Gamer-affine Nutzer wie deine Mutter)? Tendenz: ja, sparsam. Schema (`cosmetics.cost_sparks`) ist offen gelassen.
