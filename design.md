# Design — Taskly

> **Taskly** by Jason Holweg · baut auf `brand.md`
> Status: Entwurf v0.1 · Apple/Silicon-Valley-clean · Indigo · Light + Dark · Tanuki-Wärme

---

## 1. Design-Prinzipien (aus Brand abgeleitet)

1. **Ein Fokus pro Screen.** Eine Hauptaktion, klar dominant. Alles andere tritt zurück.
2. **Ruhige Basis, belohnende Pops.** UI fast monochrom & still — Belohnungsmomente dürfen knallen. Der Kontrast macht die Belohnung wertvoll.
3. **Ma (間) = Leerraum als Feature.** Großzügiger Weißraum reduziert Reizüberflutung. Lieber leer als voll.
4. **Native-Feel.** Apple-Graustufen, Apple-Radien, 44px Tap-Targets, System-Body-Font → fühlt sich an wie eine echte iOS-App, nicht wie eine Website.
5. **Tanuki trägt Emotion, nicht die UI.** Wärme kommt vom Maskottchen & Motion, nicht von bunter Chrome.

## 2. Farb-Tokens

Indigo nah an Apples System-Indigo, Neutrals = Apple-Graustufen. Gold für Sparks. **Rot fast verbannt** (nur destruktiv, gedämpft).

### Light Mode (`:root`)
```css
:root {
  /* Indigo (Marke) */
  --indigo-50:  #EEEEFB;
  --indigo-100: #E0E0F9;
  --indigo-200: #C4C3F3;
  --indigo-300: #9F9CEC;
  --indigo-400: #7C77E6;
  --indigo-500: #5F58E0;   /* Brand-Base */
  --indigo-600: #4F47D6;   /* Primär-Aktion */
  --indigo-700: #413AB8;
  --indigo-800: #353093;
  --indigo-900: #2A2670;

  --color-primary:         var(--indigo-600);
  --color-primary-hover:   var(--indigo-700);
  --color-primary-subtle:  var(--indigo-50);
  --color-on-primary:      #FFFFFF;

  /* Neutrals (Apple-Graustufen) */
  --color-bg:            #F5F5F7;   /* App-Hintergrund */
  --color-surface:       #FFFFFF;   /* Cards */
  --color-surface-2:     #FAFAFC;
  --color-border:        #E5E5EA;
  --color-text:          #1D1D1F;
  --color-text-secondary:#6E6E73;
  --color-text-tertiary: #A1A1A6;

  /* Belohnung */
  --color-spark:         #F5A623;   /* Sparks / Währung */
  --color-spark-bright:  #FFB93D;
  --color-celebrate:     #FF5C8A;   /* Level-Up / Konfetti-Pop */
  --color-xp:            var(--indigo-500);

  /* Semantik */
  --color-success:       #2FBF71;   /* erledigt / Fortschritt */
  --color-due-soon:      #F2A33C;   /* Deadline naht — KEIN Rot */
  --color-danger:        #E0483D;   /* nur destruktiv, sparsam */
}
```

### Dark Mode (`[data-theme="dark"]`)
```css
[data-theme="dark"] {
  --color-primary:         #6E68F0;   /* heller fürs Dunkle */
  --color-primary-hover:   #837EF3;
  --color-primary-subtle:  #1E1B3A;

  --color-bg:            #000000;
  --color-surface:       #1C1C1E;
  --color-surface-2:     #2C2C2E;
  --color-border:        #38383A;
  --color-text:          #F5F5F7;
  --color-text-secondary:#98989D;
  --color-text-tertiary: #636366;

  --color-spark:         #FFB93D;
  --color-celebrate:     #FF6F9C;
  --color-success:       #32D27A;
  --color-due-soon:      #F7B250;
}
```

## 3. Typografie

- **Body / UI:** `Hanken Grotesk`, dann System-Stack (`-apple-system, BlinkMacSystemFont, "SF Pro Text"`) → clean, neutral, nativ.
- **Display / Brand / Gamification:** `Nunito` → rund, warm, freundlich (passt zum Tanuki), aber erwachsen — bewusst **anders** als Glacelias Baloo 2.

```css
:root {
  --font-body: "Hanken Grotesk", -apple-system, BlinkMacSystemFont, "SF Pro Text", sans-serif;
  --font-display: "Nunito", var(--font-body);
}
```

| Token | Größe / Line | Weight | Font | Einsatz |
|---|---|---|---|---|
| `display` | 34 / 40 | 800 | display | „Was jetzt?"-Hero, Level-Up |
| `h1` | 28 / 34 | 700 | display | Screen-Titel |
| `h2` | 22 / 28 | 700 | display | Sektionen |
| `h3` | 18 / 24 | 600 | display | Card-Titel |
| `body` | 16 / 24 | 400 | body | Fließtext |
| `body-strong` | 16 / 24 | 600 | body | betont |
| `caption` | 13 / 18 | 500 | body | Meta, Labels |
| `mono-num` | 16 | 700 | display | XP/Sparks-Zähler |

## 4. Spacing & Form

```css
:root {
  --space-1: 4px;  --space-2: 8px;  --space-3: 12px; --space-4: 16px;
  --space-5: 24px; --space-6: 32px; --space-7: 48px; --space-8: 64px;

  --radius-sm: 8px;  --radius-md: 12px; --radius-lg: 16px;
  --radius-xl: 20px; --radius-2xl: 28px; --radius-full: 999px;

  --tap-min: 44px;            /* Apple HIG Mindest-Tap-Target */
  --content-max: 480px;       /* App-Spalte (mobile-first) */
}
```
- Cards: `--radius-xl` (20px), Buttons: `--radius-md`–`lg`, Pills/Chips: `--radius-full`.
- Standard-Außenabstand der App-Spalte: `--space-4` (16px).

## 5. Schatten / Elevation (Apple-soft)

```css
:root {
  --shadow-sm: 0 1px 2px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.06);
  --shadow-md: 0 4px 12px rgba(0,0,0,.06), 0 2px 4px rgba(0,0,0,.04);
  --shadow-lg: 0 12px 32px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.05);
}
[data-theme="dark"] {
  --shadow-sm: 0 1px 2px rgba(0,0,0,.4);
  --shadow-md: 0 4px 16px rgba(0,0,0,.5);
  --shadow-lg: 0 16px 40px rgba(0,0,0,.6);
}
```
Sparsam einsetzen — clean heißt: meist nur `--shadow-sm`, `lg` nur für schwebende Elemente (Modals, Lootkiste).

## 6. Motion-Tokens

Zwei Geschwindigkeiten: **ruhige UI** (dezent) vs. **Belohnung** (springy, verspielt).

```css
:root {
  --dur-fast: 150ms; --dur-base: 220ms; --dur-slow: 380ms; --dur-reward: 600ms;
  --ease-standard: cubic-bezier(.4,0,.2,1);
  --ease-out:      cubic-bezier(0,0,.2,1);
  --ease-spring:   cubic-bezier(.34,1.56,.64,1);  /* Belohnungs-Pop */
}
@media (prefers-reduced-motion: reduce) {
  * { animation: none !important; transition-duration: .01ms !important; }
}
```
- **UI-Übergänge:** `--dur-base` + `--ease-standard`. Unauffällig.
- **Belohnung** (Erledigt-Haken, Spark-Zähler, Lootkiste, Level-Up): `--ease-spring`, länger, mit Pop/Konfetti. Das sind die kaufbaren Animationen → austauschbar gehalten (CSS-Klassen-Varianten).

## 7. Kern-Komponenten (Spezifikation)

**„Was jetzt?"-Hero** — der wichtigste Screen.
Eine große Card, mittig, viel Leerraum drumherum. Oben kleiner Tanuki + ein Satz Begründung. Mitte: die *eine* Aufgabe groß (`display`). Unten drei Aktionen: **Erledigt** (primär, voll), **Skip** & **Snooze** (tertiär, dezent). Davor: die 2 Quick-Buttons (Zeit 10/30/60 · Energie müde/ok/voll) als Pills.

**Quick-Select Pills** — `--radius-full`, inaktiv = `--color-surface-2` + Border, aktiv = `--color-primary-subtle` + Indigo-Text/Border. Tap-Target ≥ 44px.

**Task-Card (Liste/Plan)** — `--radius-xl`, `--shadow-sm`. Links Domain-Punkt (Haushalt/Privat/Business/Termin = je eine ruhige Farbe), Titel, darunter Meta-Chips (Zeit · Energie · XP). Rechts Erledigt-Tap. Swipe: erledigt / bearbeiten.

**Spark- & XP-Zähler** — oben fix. XP als Indigo-Progressring zum nächsten Level, Sparks als Gold-Zahl mit Funken-Icon. Zählt animiert hoch (`--ease-spring`).

**Streak-Indikator** — Tanuki-State + Tageszahl. Wird mit Streak „wärmer". Bruch = tröstende Animation, **nie** trauriges Rot/Drama.

**Lootkiste** — Modal, `--shadow-lg`, dunkler Backdrop. Öffnungs-Animation mit `--ease-spring` + `--color-celebrate`-Konfetti. Hier darf's am meisten knallen.

**Leaderboard-Row** — Familienmitglied: Avatar/Tanuki, Name, Level, XP-Balken. Eigene Zeile dezent indigo hervorgehoben. Kein aggressives Ranking-Rot.

**Nudge-Card (in-app Repräsentation der WhatsApp/Push-Message)** — Tanuki + ein Satz + ein Tap („Mach's" → springt in „Was jetzt?").

**Bottom-Nav** — max. 4 Items: Heute · Plan · Shop · Mehr. Aktiv = Indigo, inaktiv = `--color-text-tertiary`.

## 8. Domain-Farben (Aufgaben-Typen)

Ruhig, nicht knallig (sie sind Orientierung, keine Belohnung):
| Domain | Farbe (Light) |
|---|---|
| Haushalt | `#5AC8C8` (ruhiges Teal) |
| Privat | `--indigo-400` |
| Business | `#7E8AA0` (gedämpftes Slate) |
| Termin | `#C8924A` (warmes Sand) |

## 9. Tanuki-Integration

- **Plätze:** „Was jetzt?"-Hero (klein, oben), Erledigt-Moment, Leerzustand, Streak-Indikator, Nudge-Card, Lootkiste.
- **Größen:** `sm` 32px (inline), `md` 64px (Cards), `lg` 120px+ (Hero/Leerzustand/Reward).
- **States (Asset-Set, das Jason liefert):** neutral · feiernd · müde/ruhig · tröstend (Streak-Bruch) · saisonal (Kosmetik).
- **Technisch:** als austauschbare Asset-Layer (PNG/SVG/Lottie), damit Spark-Shop-Kosmetik einfach Varianten tauscht.
- **Japanische Themes:** dezent, als Spark-Shop-Kategorie (Sakura, Jahreszeiten) — färben Akzente/Hintergrund-Motive, **nie** die clean Basis.

## 10. Accessibility & Native-Details

- Kontrast: Text ≥ 4.5:1 (Primär-Buttons geprüft).
- Tap-Targets ≥ 44×44px.
- `prefers-reduced-motion` respektiert (Belohnungs-Animationen reduziert, nicht aus).
- `prefers-color-scheme` → Auto Light/Dark, plus manueller Toggle.
- Safe-Area-Insets (`env(safe-area-inset-*)`) für iOS-Notch/Home-Indicator.
- PWA: `theme-color` an Light/Dark koppeln, Standalone-Display.

## 11. Offene Design-Fragen

1. **Body-Font:** `Hanken Grotesk` ok, oder lieber rein System-Font (noch nativer, aber weniger eigener Charakter)?
2. **Display-Font:** `Nunito` ok, oder willst du dir eine Alternative ansehen (z. B. Onest, Plus Jakarta)?
3. **Indigo-Ton:** `#4F47D6` als Primär — soll ich dir die Palette live als Style-Tile rendern, damit du den echten Ton + Light/Dark siehst, bevor wir's festnageln?
