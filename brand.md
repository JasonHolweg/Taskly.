# Brand — Taskly

> **Taskly** by Jason Holweg · `taskly.jasonholweg.de`
> Status: Entwurf v0.1 · Quelle der Wahrheit für Ton, Name, Identität · Tokens → `design.md`

---

## 1. Name

**Taskly** — „Task" + das `-ly`-Suffix, das es leicht, beiläufig, mühelos macht. Nicht „Task-*manager*" (klingt nach Arbeit, nach Verwaltung, nach Überforderung), sondern Taskly: Aufgaben, aber locker. Genau die Haltung, die ADHS braucht.

Vollname/Signatur: **Taskly by Jason Holweg**.

## 2. Positionierung (ein Satz)

> Taskly ist die Anti-Überforderungs-App: Statt dir deine ganze To-do-Liste vorzuhalten, sagt sie dir **eine Sache, die du jetzt machen kannst** — und feiert dich dafür.

## 3. Wofür wir stehen — und wogegen

**Dafür:**
- **Eine Sache, jetzt.** Klarheit statt Auswahl.
- **Erledigen wird belohnt**, nicht eingefordert.
- **Die App denkt, du machst.** Kein Planungs-Stress beim User.
- **Kleine Siege zählen.** 5 Minuten sind ein Win.

**Dagegen (Anti-Brand):**
- ❌ **Schuld & Druck.** Keine roten „8 überfällige Aufgaben!"-Badges, kein Vorwurfs-Ton.
- ❌ **Überladung.** Nie zehn Dinge gleichzeitig zeigen.
- ❌ **Corporate-Produktivität.** Taskly ist kein Asana, kein Jira, kein „Hustle".
- ❌ **Kindisch.** Verspielt ja, infantil nein.

## 4. Persönlichkeit & Stimme

Taskly ist der **ruhige, ermutigende Kumpel**, der nie nervt. Er sieht das Chaos, macht aber keinen Stress draus. Er reicht dir genau eine Sache und sagt „komm, das kriegst du." Wenn's nicht klappt, ist das ok — morgen wieder.

**Vier Stimm-Attribute:** ruhig · direkt · warm · nie nörgelnd.

**Tonregeln:**
- Kurz. ADHS liest keine Absätze.
- Du-Form, locker, deutsch.
- Konkret statt abstrakt: „Mach kurz den Abwasch" statt „Du hast offene Aufgaben".
- Feiern ohne Übertreibung. Echt, nicht aufgesetzt-motivierend.
- **Nie** Schuld erzeugen. Verpasstes ist nie ein Vorwurf.

## 5. Ton in Schlüssel-Momenten (markenkritisch)

Hier entscheidet sich die Marke. Beispiele:

**Nudge (WhatsApp/Push)**
- ✅ „Hast 15 Min? Mach kurz den Abwasch — 20 XP. 💪"
- ❌ „Erinnerung: 8 Aufgaben offen. 3 überfällig."

**„Was jetzt?"-Vorschlag**
- ✅ „Wenig Akku? Dann nimm was Leichtes: Wäsche umräumen, 5 Min."
- ❌ „Höchste Priorität: Steuererklärung."

**Aufgabe erledigt**
- ✅ „Erledigt. +20 XP. Eine weniger im Kopf. ✨"
- ❌ „Gut gemacht! Du hast jetzt nur noch 7 Aufgaben."

**Streak gebrochen** *(der wichtigste Moment — hier wird die Marke gemacht oder gebrochen)*
- ✅ „Streak ist weg — passiert. Heute eine Sache, und wir starten neu."
- ❌ „Oh nein! Deine 12-Tage-Streak ist verloren! 😢"

**Leerer Zustand / nichts zu tun**
- ✅ „Nichts dringend. Genieß die Pause."
- ❌ „Du hast keine Aufgaben geplant. Plane jetzt deine Woche!"

> Regel: Taskly belohnt **Handlung**, normalisiert **Aussetzer**, und macht **nie** Druck. Detaillierte Copy-Patterns → `rules.md`.

## 6. Visuelle Richtung (Tokens kommen in `design.md`)

Die Identität muss die Kern-Spannung tragen: **ruhige Basis + belohnende Pop-Momente.**

- **Grund-UI:** **Apple / Silicon-Valley-clean** — so reduziert wie möglich, viel Weißraum, wenig gleichzeitig sichtbar (gut gegen Reizüberflutung). Gleiche handwerkliche Schärfe wie Glacelia. Glücksfall: japanischer Minimalismus (*ma* = bewusster Leerraum, Zurückhaltung) und Apple-Ästhetik teilen dieselbe DNA — Basis bleibt clean, die japanische Wärme lebt im Maskottchen & in den Kosmetik-Themes (§9).
- **Belohnungs-Momente:** wenn etwas erledigt wird / Lootkiste aufgeht / Level steigt → hier darf es **knallen** (Farbe, Motion, Sound). Der Kontrast zur ruhigen Basis macht die Belohnung wertvoll.
- **Farb-Konzept (Vorschlag, final in `design.md`):**
  - *Primär:* **Indigo** (Fokus, Vertrauen, Ruhe) — eigene Marke, klar **anders** als Glacelias Violett. ✅ gelockt.
  - *Belohnungs-Akzent:* warm & feierlich (Gold/Amber für Coins, plus ein lebendiger Pop für Level-Up/Erledigt).
  - *Semantik:* klares Grün = erledigt/Fortschritt; sanftes Warnsignal **ohne** aggressives Rot (kein Schuld-Rot).
  - Light + Dark Mode von Anfang an.
- **Typografie:** freundlich, rund, gut lesbar — eine Display-Schrift mit Charakter fürs Spielerische, eine ruhige für Fließtext. (Kandidaten → `design.md`.)
- **Icons:** schlicht, lucide-Stil, konsistent (wie bei Glacelia bewährt).
- **Motion:** ist hier **Feature, nicht Deko** (Erledigt-Animationen & Streak-Animationen sind kaufbare Kosmetik). Grundprinzip: ruhige UI = dezente Übergänge; Belohnung = spielerische, befriedigende Micro-Animationen.

## 7. In-App-Naming (Vorschläge — bitte gegenchecken)

| Element | Vorschlag | Alternativen |
|---|---|---|
| Währung | **Sparks** ✅ | — |
| Lootkiste | **Kiste** / **Loot** | Schatzkiste, Drop |
| Der zentrale Button | **„Was jetzt?"** | „Los geht's", „Eine Sache" |
| Energielevel | müde / ok / voll | — |
| Erledigt-Moment | **„Eine weniger im Kopf"** (Tagline-Gefühl) | — |

> Profilrahmen, App-Icons, Streak- & Erledigt-Animationen = die Kosmetik-Kategorien aus `concept.md` §8.

## 8. Brand-Anti-Patterns (was die Marke sofort bricht)

1. Eine **Liste** als Antwort auf „Was jetzt?".
2. **Rote Zahlen / Overdue-Badges**, die Schuld erzeugen.
3. **Nag-Notifications** im Stil „Du hast lange nichts gemacht."
4. **Pay-to-win** oder Echtgeld-Kosmetik in v1.
5. Belohnungs-Inflation (alles gibt Konfetti → nichts fühlt sich mehr wertvoll an).
6. Mehr als eine Hauptaktion pro Screen.

---

## 9. Maskottchen — Tanuki

Taskly bekommt einen **Tanuki** (japanischer Marderhund) als Buddy. In der japanischen Folklore steht der Tanuki für Glück, Sorglosigkeit und gute Laune — perfekt für den „ruhigen, ermutigenden Kumpel"-Ton aus §4. Er verkörpert die Stimme der App: feiert mit dir, macht aber nie Druck.

> **Warum das zählt:** Taskly ist für Jasons Mutter gedacht, die die japanische Kultur liebt. Der Tanuki und die dezenten japanischen Elemente sind kein Deko-Gimmick, sondern der emotionale Kern der Marke.

**Rolle des Tanuki:**
- **Stimm-Träger** — taucht in Nudges, Erledigt-Momenten, Leerzuständen auf (kleine Reaktion/Pose statt Text-Wall).
- **Belohnungs-Quelle (Garderobe)** — Outfits, je mit eigenen **Posen & Mimiken**. Da Jason die Bilder selbst generiert: quasi unbegrenzte Kosmetik-Tiefe aus *einem* Charakter. Hauptkategorie des Spark-Shops.
- **Streak-Begleiter** — wird mit längerer Streak „wärmer"/glücklicher; bei gebrochener Streak **tröstend, nie enttäuscht** (zahlt direkt auf §5 ein).

**Stil:** clean, minimalistisch, freundlich — passend zur Apple-cleanen Basis, **nicht** laut/anime. Dezente japanische Themes (Sakura, Jahreszeiten) als Lootbox-Kategorie.

> **Maskottchen liegt vor** ✅ — freundlicher, runder Tanuki in warmen Tan-/Brauntönen, winkend. Funktioniert direkt als **App-Icon**. Die warmen Töne sind das perfekte Komplementär zum kühlen Indigo der UI. Weitere Outfits/Posen/Mimiken generiert Jason auf dieser Basis.

**Glücksumschlag (Belohnungs-Container):** Level-Ups & Streak-Meilensteine geben einen Umschlag, der sich öffnet → Sparks. Im japanischen Gewand: **pochibukuro** (お年玉-Umschläge). *Hinweis:* die klassisch **roten** Geld-Umschläge sind chinesisch (*hóngbāo*); das japanische Pendant (*otoshidama* in *pochibukuro*) ist meist weiß/gemustert. Für maximale Authentizität → pochibukuro-Look; für visuellen Punch ggf. bewusst rot. Jasons Call.

**Vision (v2+):** „Tanuki Reisen" — Tanuki geht auf Reisen, findet Items, wird stärker (Idle-RPG-lite). Details & die zentrale Design-Regel in `concept.md` §14.

## 10. Getroffene Entscheidungen (gelockt)

1. **Primärfarbe:** Indigo. ✅
2. **Stil:** Apple/Silicon-Valley-clean, so reduziert wie möglich (Glacelia-Niveau). ✅
3. **Logo / App-Icon:** Wortmarke „Taskly"; App-Icon = das Tanuki-Maskottchen. ✅
4. **Währung:** **Sparks**. ✅
5. **Maskottchen:** **Tanuki** liegt vor (warme Tan-Töne, freundlich), dient auch als App-Icon. ✅
