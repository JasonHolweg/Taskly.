/* Taskly — Frontend-Logik (Vanilla JS). */
'use strict';

const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

const DOMAIN_COLOR = {
  haushalt: 'var(--dom-haushalt)', privat: 'var(--dom-privat)',
  business: 'var(--dom-business)', termin: 'var(--dom-termin)',
};

/* ---------- API ---------- */
async function api(path, method = 'GET', data = null) {
  const opts = { method, headers: {}, credentials: 'same-origin' };
  if (data) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(data); }
  const res = await fetch('/api/' + path, opts);
  let json = {};
  try { json = await res.json(); } catch (_) {}
  if (!res.ok) throw new Error(json.error || 'Fehler');
  return json;
}

/* ---------- Theme ---------- */
function effectiveDark() {
  const t = document.documentElement.getAttribute('data-theme');
  if (t === 'dark') return true;
  if (t === 'light') return false;
  return matchMedia('(prefers-color-scheme: dark)').matches;
}
function updateThemeChrome() {
  const t = document.documentElement.getAttribute('data-theme');
  const btn = $('#theme-toggle');
  if (btn) {
    btn.textContent = t === 'light' ? '☀️' : t === 'dark' ? '🌙' : '◐';
    btn.title = 'Theme: ' + (t === 'light' ? 'Hell' : t === 'dark' ? 'Dunkel' : 'Automatisch');
  }
  let m = document.querySelector('meta[name="theme-color"]:not([media])');
  if (!m) { m = document.createElement('meta'); m.setAttribute('name', 'theme-color'); document.head.appendChild(m); }
  m.setAttribute('content', effectiveDark() ? '#000000' : '#F5F5F7');
}
function initTheme() {
  const saved = localStorage.getItem('taskly-theme');
  if (saved === 'dark' || saved === 'light') document.documentElement.setAttribute('data-theme', saved);
  updateThemeChrome();
  matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', updateThemeChrome);
  $('#theme-toggle')?.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme');   // null | light | dark
    const next = !cur ? 'light' : cur === 'light' ? 'dark' : '';        // Auto → Hell → Dunkel → Auto
    if (next) document.documentElement.setAttribute('data-theme', next);
    else document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('taskly-theme', next);
    updateThemeChrome();
  });
}

/* ---------- Sound (Tanuki-Stimme + Effekte) ---------- */
const SOUND = {
  on: localStorage.getItem('taskly-sound') !== 'off',   // Standard: an
  els: {},
  primed: false,
  list: ['vo-komm', 'vo-eine-sache', 'vo-schoen', 'vo-klein-anfangen', 'vo-hihi', 'vo-kitzeln',
    'sfx-chest-click', 'sfx-stab', 'sfx-levelup', 'sfx-win'],
};
function soundEl(name) {
  let a = SOUND.els[name];
  if (!a) { a = new Audio('/assets/sounds/' + name + '.mp3'); a.preload = 'auto'; SOUND.els[name] = a; }
  return a;
}
// iOS: HTMLAudio muss einmal in einer echten Geste „freigeschaltet" werden,
// damit späteres Abspielen (z.B. nach await beim Level-Up) erlaubt bleibt.
function primeSounds() {
  if (SOUND.primed) return; SOUND.primed = true;
  SOUND.list.forEach(n => {
    const a = soundEl(n); a.muted = true;
    const p = a.play();
    if (p && p.then) p.then(() => { a.pause(); a.currentTime = 0; a.muted = false; }).catch(() => { a.muted = false; });
    else { try { a.pause(); } catch (_) {} a.muted = false; }
  });
}
function playSound(name) {
  if (!SOUND.on) return;
  try { const a = soundEl(name); a.currentTime = 0; const p = a.play(); if (p && p.catch) p.catch(() => {}); } catch (_) {}
}
// Genau eine Tanuki-Stimme gleichzeitig (kein Übereinanderreden).
function playVoice(name) {
  if (!SOUND.on) return;
  SOUND.list.forEach(n => {
    if (n.startsWith('vo-') && SOUND.els[n]) { try { SOUND.els[n].pause(); SOUND.els[n].currentTime = 0; } catch (_) {} }
  });
  playSound(name);
}
function initSound() {
  document.addEventListener('pointerdown', primeSounds, { once: true });
  const btn = $('#sound-toggle');
  if (!btn) return;
  const sync = () => { btn.textContent = SOUND.on ? '🔊 Töne an' : '🔇 Töne aus'; btn.setAttribute('aria-pressed', String(SOUND.on)); };
  sync();
  btn.addEventListener('click', () => {
    SOUND.on = !SOUND.on;
    localStorage.setItem('taskly-sound', SOUND.on ? 'on' : 'off');
    sync();
    if (SOUND.on) playVoice('vo-hihi');   // kleine hörbare Bestätigung
  });
}

/* ---------- Progressive Freischaltung (journey.md §8) ----------
   L1–2 nur Kern-Loop · L3 Reise + Tanuki-Hub · L4 Shop/Rahmen/Kisten.
   Flag aus → exakt v1-Verhalten (alles sichtbar außer Reise). */
let GATES = { enabled: false, advLevel: 3, shopLevel: 4 };
function applyGates(level) {
  const adv  = GATES.enabled && level >= GATES.advLevel;
  const shop = !GATES.enabled || level >= GATES.shopLevel;
  $('#nav-journey')?.classList.toggle('hidden', !adv);
  $('#nav-shop')?.classList.toggle('hidden', GATES.enabled && level < GATES.advLevel);
  $$('.cust-cat').forEach(b => {
    if (b.dataset.cat === 'frames' || b.dataset.cat === 'shop') b.classList.toggle('hidden', !shop);
  });
}

const fmtKm = km => (+km).toLocaleString('de-DE', { maximumFractionDigits: 1 });
// Der ruhige Heute-Einzeiler — einziges Reise-Element im Kern (build-Brief §2).
function updateJourneyLine(j) {
  const el = $('#journey-line'); if (!el) return;
  if (j && j.dest_name != null) {
    el.textContent = `🦝 noch ${fmtKm(j.remaining_km)} km bis ${j.dest_name}`;
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}

/* ---------- State / Header ---------- */
function renderProgress(p) {
  if (!p) return;
  $('#xp-level').textContent = p.level;
  const pct = p.xp_needed ? Math.round((p.xp_into / p.xp_needed) * 100) : 0;
  $('#xp-ring').style.setProperty('--p', pct);
  $('#spark-count').textContent = p.sparks;
  $('#streak-count').textContent = p.streak;
  const flame = $('.streak-flame'); if (flame) flame.textContent = p.frozen ? '🧊' : '🔥';
  applyGates(+p.level || 1);
}

function showIce(s) {
  const b = $('#ice-banner'); const p = s.progress;
  FROZEN = !!(p && p.frozen);
  if (p && p.frozen) {
    let hrs = '';
    if (p.frozen_until) {
      const ms = new Date(p.frozen_until.replace(' ', 'T')) - new Date();
      if (ms > 0) hrs = ` Noch ~${Math.ceil(ms / 3.6e6)} h.`;
    }
    const r = s.rescues || [];
    const cheer = r.length ? ` 💛 ${r.join(', ')} ${r.length > 1 ? 'feuern' : 'feuert'} dich an!` : '';
    b.innerHTML = `🧊 Deine Streak (${p.streak}) ist auf Eis — mach <b>eine Sache</b>, dann läuft sie weiter.${hrs}${cheer}`;
    b.classList.remove('hidden');
  } else {
    b.classList.add('hidden');
  }
}

/* ---------- Auth ---------- */
function showAuth() { $('#auth').classList.remove('hidden'); $('#app').classList.add('hidden'); }
function showApp()  { $('#auth').classList.add('hidden');   $('#app').classList.remove('hidden'); }

function initAuth() {
  $$('[data-auth-tab]').forEach(btn => btn.addEventListener('click', () => {
    $$('[data-auth-tab]').forEach(b => b.classList.toggle('is-active', b === btn));
    const tab = btn.dataset.authTab;
    $('#form-login').classList.toggle('hidden', tab !== 'login');
    $('#form-register').classList.toggle('hidden', tab !== 'register');
    $('#auth-error').textContent = '';
  }));

  $('#form-login').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    try {
      await api('auth/login.php', 'POST', { email: f.email.value, password: f.password.value });
      await boot();
    } catch (err) { $('#auth-error').textContent = err.message; }
  });

  $('#form-register').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    try {
      await api('auth/register.php', 'POST', {
        name: f.name.value, email: f.email.value,
        password: f.password.value, invite_code: f.invite_code.value,
      });
      await boot();
    } catch (err) { $('#auth-error').textContent = err.message; }
  });
}

/* ---------- Brain-Dump ---------- */
// Onboarding (kein Tasks) vs. normaler Hero
function applyFirstRun(hasTasks) {
  $('#onboarding').classList.toggle('hidden', hasTasks);
  $('.hero').classList.toggle('hidden', !hasTasks);
}

function openDump() {
  $('#dump-result').textContent = '';
  $('#dump-sheet').classList.remove('hidden');
  setTimeout(() => $('#dump-text').focus(), 50);
}
function closeDump() {
  $('#dump-sheet').classList.add('hidden');
  $('#dump-text').blur();
}
function initDump() {
  $('#onb-start').addEventListener('click', openDump);
  $('#dump-close').addEventListener('click', closeDump);
  $('#dump-sheet').addEventListener('click', e => { if (e.target.id === 'dump-sheet') closeDump(); });

  $('#dump-save').addEventListener('click', async () => {
    const text = $('#dump-text').value.trim();
    if (!text) return;
    const btn = $('#dump-save');
    btn.disabled = true; btn.textContent = 'Sortiere…';
    try {
      const r = await api('braindump.php', 'POST', { text });
      $('#dump-text').value = '';
      const word = r.count === 1 ? 'Aufgabe' : 'Aufgaben';
      $('#dump-result').textContent = `${r.count} ${word} erfasst${r.ai_used ? '' : ' (ohne KI)'} ✓`;
      applyFirstRun(true);
      resetHero();
      if (!$('#view-plan').classList.contains('hidden')) loadWeek();   // Plan offen → neue Aufgaben sofort zeigen
      setTimeout(closeDump, 1000);
    } catch (err) { $('#dump-result').textContent = err.message; }
    finally { btn.disabled = false; btn.textContent = 'Erfassen'; }
  });

  // Voice als Progressive Enhancement (architecture.md §4.0)
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  let startVoice = null;
  if (SR) {
    const vbtn = $('#dump-voice'); vbtn.hidden = false;
    const label = vbtn.querySelector('.voice-label');
    const rec = new SR(); rec.lang = 'de-DE'; rec.interimResults = false;
    let listening = false, audio = null;

    // Sanfter Sinus-Beep als hörbares Start-/Stopp-Signal (iOS-tolerant: fehlschlagen schadet nicht).
    function ensureAudio() {
      try {
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === 'suspended') audio.resume();
      } catch (_) {}
    }
    function tone(freq, dur) {
      if (!audio) return;
      try {
        const t0 = audio.currentTime, o = audio.createOscillator(), g = audio.createGain();
        o.type = 'sine'; o.frequency.value = freq;
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(0.16, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        o.connect(g).connect(audio.destination);
        o.start(t0); o.stop(t0 + dur + 0.02);
      } catch (_) {}
    }
    function setState(s) { // 'idle' | 'listening' | 'hearing'
      vbtn.classList.toggle('is-listening', s !== 'idle');
      vbtn.classList.toggle('is-hearing', s === 'hearing');
      label.textContent = s === 'hearing' ? 'Ich höre dich …'
        : s === 'listening' ? 'Hört zu – tippen zum Stoppen'
        : '🎙️ Sprechen';
    }

    startVoice = () => {
      if (listening) return;
      ensureAudio();                 // im User-Gesten-Kontext entsperren (iOS)
      try { rec.start(); } catch (_) {}
    };
    vbtn.addEventListener('click', () => {
      if (listening) { try { rec.stop(); } catch (_) {} }
      else startVoice();
    });

    rec.onstart = () => { listening = true; setState('listening'); tone(660, 0.12); };
    rec.onsoundstart = () => setState('hearing');
    rec.onspeechstart = () => setState('hearing');
    rec.onspeechend = () => { if (listening) setState('listening'); };
    rec.onresult = e => {
      const t = [...e.results].map(r => r[0].transcript).join(' ');
      const ta = $('#dump-text'); ta.value = (ta.value ? ta.value + ' ' : '') + t;
    };
    rec.onend = () => { if (listening) tone(440, 0.1); listening = false; setState('idle'); };
    rec.onerror = e => {
      listening = false; setState('idle');
      if (e && (e.error === 'not-allowed' || e.error === 'service-not-allowed'))
        $('#dump-result').textContent = 'Mikrofon ist blockiert – bitte in den Einstellungen erlauben.';
    };
  }

  // Mikrofon-Button (Hero): Sheet öffnen UND – wenn unterstützt – sofort lauschen,
  // synchron im selben Klick, damit iOS die Aufnahme-Geste nicht verwirft.
  $('#mic-btn').addEventListener('click', () => {
    openDump();
    if (startVoice) startVoice();
  });
}

/* ---------- Quick-Selects ---------- */
function initQuick() {
  $$('.quick-group').forEach(group => {
    group.addEventListener('click', e => {
      const pill = e.target.closest('.pill');
      if (!pill) return;
      $$('.pill', group).forEach(p => p.classList.toggle('is-active', p === pill));
    });
  });
  // Optionaler Filter „Zeit & Akku" — standardmäßig eingeklappt
  $('#filter-toggle')?.addEventListener('click', () => {
    const open = $('#quick').classList.toggle('hidden') === false;
    $('#filter-toggle').setAttribute('aria-expanded', String(open));
    $('#filter-toggle').classList.toggle('open', open);
  });
}
// Tageszeit-abhängige Begrüßung (kein Zwang zur Vorauswahl mehr).
function setGreeting() {
  const h = new Date().getHours();
  const g = h < 5 ? 'Lass uns vor dem Schlafengehen noch eine Sache abhaken.'
    : h < 11 ? 'Lass uns produktiv in den Tag starten.'
    : h < 14 ? 'Lass uns eine Sache erledigen.'
    : h < 18 ? 'Lass uns etwas von deiner Liste schaffen.'
    : h < 22 ? 'Lass uns vor dem Feierabend noch eine Kleinigkeit erledigen.'
    : 'Lass uns vor dem Schlafengehen noch eine Sache abhaken.';
  const el = $('#hero-greeting'); if (el) el.textContent = g;
}
function ctx() {
  const t = $('#q-time .pill.is-active'), e = $('#q-energy .pill.is-active');
  return { time: t ? +t.dataset.val : 30, energy: e ? e.dataset.val : 'ok' };
}

/* ---------- Was jetzt? ---------- */
function resetHero() {
  $('#hero-pick').classList.add('hidden');
  $('#hero-empty').classList.add('hidden');
  $('#hero-start').classList.remove('hidden');
  setGreeting();
}
function renderPick(r) {
  $('#hero-start').classList.add('hidden');
  if (!r.pick) {
    $('#hero-pick').classList.add('hidden');
    $('#empty-msg').textContent = r.message || 'Nichts dringend. Genieß die Pause. 🍵';
    $('#hero-empty').classList.remove('hidden');
    return;
  }
  const p = r.pick;
  current = p;
  $('#pick-tanuki').src = tanukiFor(MOOD_BY_ENERGY[ctx().energy] || 'neutral');
  $('#pick-title').textContent = p.title;
  $('#pick-meta').innerHTML =
    `<span class="chip"><span class="dot" style="background:${DOMAIN_COLOR[p.domain] || 'var(--dom-privat)'}"></span>${p.domain}</span>` +
    `<span class="chip">⏱ ${p.time_estimate} Min</span>` +
    `<span class="chip">🔋 ${p.energy}</span>` +
    (p.base_xp ? `<span class="chip chip-xp">+${p.base_xp} XP</span>` : '');
  // Begründung: entweder schon da (Altpfad) oder per Tipp-Animation nachladen.
  if (r.reason != null) {
    setReason(r.reason);
  } else {
    showTyping();
    loadReason(p.occ_id);
  }
  $('#hero-empty').classList.add('hidden');
  $('#hero-pick').classList.remove('hidden');
}

// Tanuki „schreibt" (WhatsApp-Stil) bis die KI-Begründung da ist.
function showTyping() {
  const el = $('#pick-reason');
  el.classList.add('is-typing');
  el.innerHTML = '<span class="typing" aria-label="Tanuki denkt nach…"><i></i><i></i><i></i></span>';
}
function setReason(text) {
  const el = $('#pick-reason');
  el.classList.remove('is-typing');
  el.textContent = text || '';
  el.classList.remove('reason-in'); void el.offsetWidth; el.classList.add('reason-in');
}
async function loadReason(occId) {
  try {
    const r = await api('reason.php', 'POST', { occ_id: occId });
    if (!current || current.occ_id !== occId) return;   // Pick wechselte → Antwort verwerfen
    setReason(r.reason);
  } catch (_) {
    if (current && current.occ_id === occId) setReason('');   // Fehler: still ausblenden
  }
}

let current = null;

function initHero() {
  $('#btn-whatnow').addEventListener('click', async () => {
    try { renderPick(await api('whatnow.php', 'POST', ctx())); }
    catch (err) { alert(err.message); }
  });
  $('#empty-again').addEventListener('click', () => resetHero());

  const heroT = $('#hero-tanuki');
  heroT.addEventListener('click', pokeTanuki);
  heroT.addEventListener('animationend', e => { if (e.animationName === 'bounce') heroT.classList.remove('tanuki-poke'); });

  $('#act-skip').addEventListener('click', async () => {
    if (!current) return;
    try { renderPick(await api('skip.php', 'POST', { occ_id: current.occ_id })); }
    catch (err) { alert(err.message); }
  });

  $('#act-snooze').addEventListener('click', async () => {
    if (!current) return;
    try { renderPick(await api('snooze.php', 'POST', { occ_id: current.occ_id, minutes: 180 })); }
    catch (err) { alert(err.message); }
  });

  $('#act-done').addEventListener('click', async () => {
    if (!current) return;
    const occ = current.occ_id;
    try {
      const r = await api('complete.php', 'POST', { occ_id: occ });
      renderProgress(r.progress);
      $('#ice-banner').classList.add('hidden');
      FROZEN = false; applyTanuki();
      showReward(r.rewards);
    } catch (err) { alert(err.message); }
  });

  $('#env-img').addEventListener('click', openEnvelope);

  $('#reward-close').addEventListener('click', async () => {
    $('#reward').classList.add('hidden');
    // Nahtlos: direkt die nächste Sache (straffrei weiter).
    try { renderPick(await api('whatnow.php', 'POST', { ...ctx(), keep: 1 })); }
    catch (_) { resetHero(); }
  });
}

/* ---------- Reward-Pop ---------- */
let PENDING_SPARKS = 0, ENV_OPENED = false;
function showReward(rw) {
  $('#reward-xp').textContent = `+${rw.xp} XP`;
  $('#reward-msg').textContent = rw.message || '';
  const extra = $('#reward-extra'); extra.innerHTML = '';
  if (rw.sparks) {
    const d = document.createElement('div');
    d.className = 'reward-spark'; d.textContent = `+${rw.sparks} ✦`;
    extra.appendChild(d);
  }
  if (rw.thawed) {
    const d = document.createElement('div');
    d.className = 'reward-badge'; d.textContent = 'Streak gerettet! 🔥 Weiter geht’s.';
    extra.appendChild(d);
  }
  if (rw.leveled_up) {
    const d = document.createElement('div');
    d.className = 'reward-badge'; d.textContent = rw.level_message || `Level ${rw.level}! 🎉`;
    extra.appendChild(d);
    playSound('sfx-levelup');
  }
  // Reise-Hauch (journey.md §8): eine leise Zeile, kein eigenes Spektakel.
  if (rw.journey && rw.journey.km_added > 0) {
    const d = document.createElement('div');
    d.className = 'reward-journey';
    d.textContent = rw.journey.arrived
      ? `🦝 Angekommen in ${rw.journey.dest_name}! 🏁`
      : `🦝 +${fmtKm(rw.journey.km_added)} km · noch ${fmtKm(rw.journey.remaining_km)} km bis ${rw.journey.dest_name}`;
    extra.appendChild(d);
    updateJourneyLine(rw.journey.arrived ? null : rw.journey);
  }
  // Glücksumschlag (Pochibukuro) — antippen zum Öffnen → Sparks
  const total = (rw.envelopes || []).reduce((s, e) => s + (e.sparks || 0), 0);
  const env = $('#reward-envelope');
  if (total > 0) {
    PENDING_SPARKS = total; ENV_OPENED = false;
    $('#env-img').src = '/assets/img/pochibukuro/sparks-closed.png';
    $('#env-img').classList.add('pochi-wiggle'); $('#env-img').classList.remove('pochi-pop');
    $('#env-cap').textContent = 'Glücksumschlag — tippen! 🧧';
    $('#env-sparks').classList.add('hidden');
    env.classList.remove('hidden');
  } else {
    env.classList.add('hidden');
  }
  confetti();
  $('#reward').classList.remove('hidden');
}
function openEnvelope() {
  if (ENV_OPENED) return;
  ENV_OPENED = true;
  $('#env-img').src = '/assets/img/pochibukuro/sparks-open.png';
  $('#env-img').classList.remove('pochi-wiggle'); $('#env-img').classList.add('pochi-pop');
  $('#env-cap').textContent = 'Aufgemacht!';
  const es = $('#env-sparks'); es.textContent = `+${PENDING_SPARKS} ✦`; es.classList.remove('hidden');
  playSound('sfx-win');
  confetti();
}
function confetti() {
  const box = $('#confetti'); box.innerHTML = '';
  const cols = ['var(--color-celebrate)', 'var(--color-spark)', 'var(--color-primary)', 'var(--color-success)'];
  for (let i = 0; i < 24; i++) {
    const c = document.createElement('i');
    c.style.left = Math.random() * 100 + '%';
    c.style.background = cols[i % cols.length];
    c.style.animationDelay = (Math.random() * 0.2) + 's';
    box.appendChild(c);
  }
}

/* ---------- Mehr: Freunde + Leaderboard ---------- */
function initMore() {
  $('#btn-logout').addEventListener('click', async () => {
    if (confirm('Abmelden?')) { await api('auth/logout.php', 'POST').catch(() => {}); location.reload(); }
  });
  $('#friend-add').addEventListener('click', addFriend);
  $('#friend-input').addEventListener('keydown', e => { if (e.key === 'Enter') addFriend(); });
  $('#acc-save').addEventListener('click', saveProfile);
  $('#acc-pw-save').addEventListener('click', savePassword);
  $('#cal-copy').addEventListener('click', async () => {
    const url = $('#cal-url').value;
    try { await navigator.clipboard.writeText(url); }
    catch (_) { $('#cal-url').select(); document.execCommand('copy'); }
    $('#cal-msg').textContent = 'Link kopiert ✓';
  });
}

async function loadMore() {
  try {
    const ov = await api('friends.php');
    renderFriends(ov);
    renderLeaderboard((await api('leaderboard.php')).rows);
    loadPush();
    loadAccount();
    loadCalendar();
  } catch (e) { alert(e.message); }
}

/* ---------- Kalender-Abo ---------- */
async function loadCalendar() {
  try {
    const c = await api('calendar.php');
    $('#cal-url').value = c.url;
    $('#cal-subscribe').href = c.webcal;
  } catch (_) {}
}

/* ---------- Konto ---------- */
async function loadAccount() {
  try { const a = await api('account.php'); $('#acc-name').value = a.name || ''; $('#acc-email').value = a.email || ''; }
  catch (_) {}
}
async function saveProfile() {
  const name = $('#acc-name').value.trim(), email = $('#acc-email').value.trim();
  try { await api('account.php', 'POST', { action: 'profile', name, email }); $('#acc-msg').textContent = 'Gespeichert ✓'; }
  catch (e) { $('#acc-msg').textContent = e.message; }
}
async function savePassword() {
  const cur = $('#acc-pw-cur').value, nw = $('#acc-pw-new').value;
  try {
    await api('account.php', 'POST', { action: 'password', current: cur, new: nw });
    $('#acc-msg').textContent = 'Passwort geändert ✓';
    $('#acc-pw-cur').value = ''; $('#acc-pw-new').value = '';
  } catch (e) { $('#acc-msg').textContent = e.message; }
}

function renderFriends(ov) {
  $('#friend-code').textContent = ov.code;
  const req = $('#friend-requests');
  if (!ov.incoming.length) { req.innerHTML = ''; }
  else {
    req.innerHTML = '<h2 class="h3 garderobe-title">Anfragen</h2>' + ov.incoming.map(r =>
      `<div class="req-row"><span>${r.name}</span><span class="req-actions">
         <button class="btn btn-primary" data-acc="${r.user_id}">Annehmen</button>
         <button class="btn btn-ghost" data-dec="${r.user_id}">Ablehnen</button></span></div>`).join('');
    req.querySelectorAll('[data-acc]').forEach(b => b.addEventListener('click',
      () => friendAction('accept', { user_id: +b.dataset.acc })));
    req.querySelectorAll('[data-dec]').forEach(b => b.addEventListener('click',
      () => friendAction('decline', { user_id: +b.dataset.dec })));
  }
}

function renderLeaderboard(rows) {
  const lb = $('#leaderboard');
  if (!rows.length) { lb.innerHTML = '<p class="muted small">Noch keine Freunde — füg welche per Code hinzu.</p>'; return; }
  lb.innerHTML = rows.map(r => {
    const medal = r.rank === 1 ? '🥇' : r.rank === 2 ? '🥈' : r.rank === 3 ? '🥉' : `${r.rank}.`;
    const streak = r.frozen ? `🧊 ${r.streak}` : (r.streak > 0 ? `🔥 ${r.streak}` : '–');
    const rescue = (r.frozen && !r.is_me)
      ? `<button class="btn btn-ghost lb-rescue" data-rescue="${r.user_id}">retten 💛</button>` : '';
    return `<div class="lb-row${r.is_me ? ' me' : ''}">
        <span class="lb-rank">${medal}</span>
        <span class="lb-name">${r.name}${r.is_me ? ' (du)' : ''}</span>
        <span class="lb-streak">${streak}</span>
        <span class="lb-lvl">Lvl ${r.level}</span>
        <span class="lb-xp">${r.xp} XP</span>
        ${rescue}
      </div>`;
  }).join('');
  lb.querySelectorAll('[data-rescue]').forEach(b => b.addEventListener('click', async () => {
    b.disabled = true;
    try { await api('rescue.php', 'POST', { user_id: +b.dataset.rescue }); b.textContent = 'gesendet ✓'; }
    catch (e) { alert(e.message); b.disabled = false; }
  }));
}

async function addFriend() {
  const code = $('#friend-input').value.trim().toUpperCase();
  if (!code) return;
  await friendAction('add', { code });
}
async function friendAction(action, extra) {
  const msg = $('#friend-msg');
  try {
    const r = await api('friends.php', 'POST', { action, ...extra });
    $('#friend-input').value = '';
    msg.textContent = r.accepted ? 'Freund hinzugefügt! 🎉'
      : r.requested ? 'Anfrage gesendet.' : '';
    if (r.overview) renderFriends(r.overview);
    renderLeaderboard((await api('leaderboard.php')).rows);
  } catch (e) { msg.textContent = e.message; }
}

/* ---------- Tanuki (equipped Outfit) ---------- */
const BASE_TANUKI = {
  neutral: '/assets/img/tanuki/base-neutral.png',
  happy: '/assets/img/tanuki/base-happy.png',
  celebrate: '/assets/img/tanuki/base-celebrate.png',
  tired: '/assets/img/tanuki/base-tired.png',
  sad: '/assets/img/tanuki/base-sad.png',
};
let EQUIPPED = null, FROZEN = false, FRAME = 'default';
function applyFrame(v) {
  FRAME = v || 'default';
  const h = $('.hero'); if (h) h.dataset.frame = FRAME;
}
function tanukiFor(emotion) {
  if (EQUIPPED && EQUIPPED.poses) {
    if (emotion === 'neutral' && EQUIPPED.poses.happy) return EQUIPPED.poses.happy;
    if (EQUIPPED.poses[emotion]) return EQUIPPED.poses[emotion];
  }
  return BASE_TANUKI[emotion] || BASE_TANUKI.neutral;
}
function applyTanuki() {
  const hero = $('#hero-tanuki'); if (hero) hero.src = FROZEN ? tanukiFor('sad') : tanukiFor('neutral');
  const pick = $('#pick-tanuki'); if (pick) pick.src = tanukiFor('neutral');
  const empty = $('#hero-empty .tanuki'); if (empty) empty.src = tanukiFor('tired');
  const rt = $('#reward-tanuki'); if (rt) rt.src = tanukiFor('celebrate');
  // Reise: Skin-Wechsel sofort übernehmen (Marker-Pose setzt renderJourney beim nächsten Render).
  const js = $('#jny-splash-img'); if (js) js.src = tanukiFor('happy');
  const jm = $('.jny-tanuki-img'); if (jm) jm.src = tanukiFor('happy');
}

// Tanuki-Mimik passend zur gewählten Energie (im Vorschlag-Zustand)
const MOOD_BY_ENERGY = { 'müde': 'tired', 'mude': 'tired', 'ok': 'neutral', 'voll': 'celebrate' };

// Antippen → kleiner Hüpfer + Spruch + passende Stimme (Persönlichkeit, brand.md §4).
// Text und Audio gehören zusammen, damit Bubble und Stimme dasselbe sagen.
const TANUKI_LINES = [
  { text: 'Komm, das kriegst du. 💪', sound: 'vo-komm' },
  { text: 'Eine Sache nach der anderen.', sound: 'vo-eine-sache' },
  { text: 'Schön, dass du da bist. 🍵', sound: 'vo-schoen' },
  { text: 'Klein anfangen zählt auch.', sound: 'vo-klein-anfangen' },
  { text: 'Hihi! 😄', sound: 'vo-hihi' },
  { text: 'Haha, hör auf, mich zu kitzeln! 😆', sound: 'vo-kitzeln' },
];
let _bubbleTimer = null;
function pokeTanuki() {
  const img = $('#hero-tanuki');
  img.classList.remove('tanuki-poke'); void img.offsetWidth; img.classList.add('tanuki-poke');
  const line = TANUKI_LINES[Math.floor(Math.random() * TANUKI_LINES.length)];
  const b = $('#tanuki-bubble');
  b.textContent = line.text;
  b.classList.add('show');
  playVoice(line.sound);
  clearTimeout(_bubbleTimer);
  _bubbleTimer = setTimeout(() => b.classList.remove('show'), 2200);
}

/* ---------- Views / Nav ---------- */
function showView(which) {
  ['today', 'plan', 'journey', 'shop', 'more'].forEach(v => {
    $('#view-' + v).classList.toggle('hidden', which !== v);
    $('#nav-' + v).classList.toggle('is-active', which === v);
  });
  if (which === 'shop') loadShop();
  if (which === 'plan') loadWeek();
  if (which === 'journey') loadJourney();
  if (which === 'more') loadMore();
}
function initNav() {
  ['today', 'plan', 'journey', 'shop', 'more'].forEach(v =>
    $('#nav-' + v).addEventListener('click', () => showView(v)));
}

/* ---------- Wochen-Plan (Tag-Auswahl + Karten, Me+-Stil) ---------- */
const DOMAIN_ICON = { haushalt: '🧹', privat: '🌿', business: '💼', termin: '📅' };
let WEEK = [], SEL = 0;

function renderWeek(days) {
  WEEK = days || [];
  if (SEL >= WEEK.length) SEL = 0;
  const w = $('#week'); w.innerHTML = '';
  WEEK.forEach((day, i) => {
    const dd = +day.date.slice(8, 10);
    const dots = day.tasks.slice(0, 3).map(t =>
      `<i style="background:${DOMAIN_COLOR[t.domain] || 'var(--dom-privat)'}"></i>`).join('');
    const btn = document.createElement('button');
    btn.className = 'wd' + (day.is_today ? ' today' : '') + (i === SEL ? ' sel' : '');
    btn.dataset.i = i;
    btn.innerHTML = `<span class="wd-name">${day.weekday}</span><span class="wd-num">${dd}</span><span class="wd-dots">${dots}</span>`;
    w.appendChild(btn);
  });
  renderDayTasks();
}

function selectDay(i) {
  SEL = i;
  $$('#week .wd').forEach(b => b.classList.toggle('sel', +b.dataset.i === i));
  renderDayTasks();
}

function dayCard(t) {
  const ico = DOMAIN_ICON[t.domain] || '🌿';
  let sub;
  if (t.type === 'termin') sub = t.fixed_at ? `${t.fixed_at.slice(11, 16)} Uhr` : 'Termin';
  else if (t.recurring)    sub = `Wiederkehrend · +${t.base_xp} XP`;
  else                     sub = `Jederzeit · +${t.base_xp} XP`;
  const bg = DOMAIN_COLOR[t.domain] || 'var(--dom-privat)';
  return `<button class="dcard${t.done ? ' done' : ''}" data-task="${t.task_id}"
      style="background:color-mix(in srgb,${bg} 15%,var(--color-surface));border-color:color-mix(in srgb,${bg} 30%,transparent)">
      <span class="dcard-ico">${ico}</span>
      <span class="dcard-body"><span class="dcard-title">${t.title}${t.recurring ? ' 🔁' : ''}</span><span class="dcard-sub">${sub}</span></span>
      <span class="dcard-check" data-check="${t.occ_id}">${t.done ? '✓' : ''}</span>
    </button>`;
}

function renderDayTasks() {
  const box = $('#day-tasks'); if (!box) return;
  const day = WEEK[SEL];
  if (!day || !day.tasks.length) {
    box.innerHTML = '<div class="day-empty-big">Nichts geplant — genieß die Pause. 🍵</div>';
    return;
  }
  const termine = day.tasks.filter(t => t.type === 'termin');
  const rest    = day.tasks.filter(t => t.type !== 'termin');
  let html = '';
  if (termine.length) html += '<div class="dsec-title">Termine</div>' + termine.map(dayCard).join('');
  if (rest.length)    html += '<div class="dsec-title">Aufgaben</div>' + rest.map(dayCard).join('');
  box.innerHTML = html;
}

async function loadWeek() {
  try { renderWeek((await api('week.php')).days); loadTasks(); }
  catch (e) { alert(e.message); }
}

async function completeOcc(occId) {
  const day = WEEK[SEL], t = day && day.tasks.find(x => x.occ_id === occId);
  if (!t || t.done) return;
  try {
    const r = await api('complete.php', 'POST', { occ_id: occId });
    if (r.progress) renderProgress(r.progress);
    const rw = r.rewards || {};
    showToast(`Erledigt! +${rw.xp || 0} XP${rw.sparks ? ` · +${rw.sparks} ✦` : ''} 🎉`);
    if (rw.journey && rw.journey.km_added > 0) updateJourneyLine(rw.journey.arrived ? null : rw.journey);
    await loadWeek();
  } catch (e) { showToast(e.message); }
}

let _toastTimer = null;
function showToast(msg) {
  const el = $('#toast'); if (!el) return;
  el.textContent = msg; el.classList.add('show');
  clearTimeout(_toastTimer); _toastTimer = setTimeout(() => el.classList.remove('show'), 2400);
}

function initPlan() {
  $('#week').addEventListener('click', e => {
    const b = e.target.closest('.wd'); if (b) selectDay(+b.dataset.i);
  });
  $('#day-tasks').addEventListener('click', e => {
    const chk = e.target.closest('[data-check]');
    if (chk) { completeOcc(+chk.dataset.check); return; }
    const card = e.target.closest('.dcard[data-task]');
    if (card && !card.classList.contains('done')) openTaskEditById(+card.dataset.task);
  });
  $('#btn-quickadd').addEventListener('click', openDump);   // schnelles Erfassen via Kopf-leeren-Sheet
  $('#btn-plan').addEventListener('click', async () => {
    const btn = $('#btn-plan'); btn.disabled = true; btn.textContent = 'Plane…';
    try { renderWeek((await api('plan_week.php', 'POST')).days); }
    catch (e) { alert(e.message); }
    finally { btn.disabled = false; btn.textContent = 'Neu planen'; }
  });
}

/* ---------- Shop ---------- */
const RAR_LABEL = { gewoehnlich: 'Gewöhnlich', selten: 'Selten', episch: 'Episch', legendaer: 'Legendär' };
const BOX_EMOJI = { japan: '🎴', kostuem: '🎁' };
const poseImg = c => c.poses.happy || c.poses.celebrate || Object.values(c.poses)[0];
let SHOP = null, CURRENT_BOX = null, LAST_DRAW = null;

async function loadShop() {
  try { SHOP = await api('shop.php'); renderShowcase(); openCat(CUST_CAT); }
  catch (e) { alert(e.message); }
}
function renderShowcase() {
  $('#shop-sparks').textContent = SHOP.sparks;
  const sc = $('#tanuki-showcase'); if (sc) sc.dataset.frame = SHOP.frame || 'default';
  const poses = EQUIPPED && EQUIPPED.poses;
  $('#tanuki-show-img').src = (poses && (poses.happy || poses.celebrate)) || '/assets/img/tanuki/base-happy.png';
  $('#tanuki-show-cap').textContent = (EQUIPPED && EQUIPPED.name) ? EQUIPPED.name : 'Basis-Tanuki';
}

/* ---- Customizing-Hub ---- */
let CUST_CAT = 'garderobe', MODAL_BOX = null;
function initCust() {
  $$('.cust-cat').forEach(b => b.addEventListener('click', () => openCat(b.dataset.cat)));
  $('#bm-close').addEventListener('click', () => $('#box-modal').classList.add('hidden'));
  $('#box-modal').addEventListener('click', e => { if (e.target.id === 'box-modal') $('#box-modal').classList.add('hidden'); });
  $('#bm-open').addEventListener('click', () => { if (MODAL_BOX) { $('#box-modal').classList.add('hidden'); openBox(MODAL_BOX); } });
}
function openCat(cat) {
  CUST_CAT = cat;
  $$('.cust-cat').forEach(b => b.classList.toggle('is-active', b.dataset.cat === cat));
  const panel = $('#cust-panel'); if (!panel || !SHOP) return;
  if (cat === 'garderobe') renderGarderobePanel(panel);
  else if (cat === 'frames') renderFramesPanel(panel);
  else if (cat === 'shop') renderShopPanel(panel);
  else renderPlaceholder(panel, cat);
}
function renderGarderobePanel(panel) {
  panel.innerHTML = '<div class="garderobe"></div>';
  const g = panel.querySelector('.garderobe');
  const base = document.createElement('div');
  base.className = 'outfit-tile base' + (SHOP.equipped_id ? '' : ' equipped');
  base.innerHTML = `<img src="/assets/img/tanuki/base-neutral.png" alt="Basis"><div class="ot-name">Basis</div>` + (SHOP.equipped_id ? '' : '<span class="ot-eq">●</span>');
  base.addEventListener('click', () => equip(0));
  g.appendChild(base);
  (SHOP.outfits || []).forEach(it => {
    const t = document.createElement('div');
    t.className = 'outfit-tile' + (it.equipped ? ' equipped' : '') + (it.owned ? '' : ' locked');
    t.dataset.rarity = it.rarity;
    t.innerHTML = `<img src="${poseImg(it)}" alt="${it.name}"><div class="ot-name">${it.name}</div>`
      + (it.equipped ? '<span class="ot-eq">●</span>' : (it.owned ? '' : '<span class="ot-lock">🔒</span>'));
    if (it.owned) t.addEventListener('click', () => equip(it.id));
    g.appendChild(t);
  });
}
const FRAME_CAT = { basis: 'Standard', prestige: 'Prestige', japan: 'Japanisch', helden: 'Helden', royal: 'Royal', drache: 'Drache', arcane: 'Magie', galaxy: 'Weltraum', cyberpunk: 'Cyberpunk', steampunk: 'Steampunk', frost: 'Frost', winter: 'Winter', halloween: 'Halloween', ozean: 'Ozean', blumen: 'Blumen', streak: 'Streak' };
function frameTile(f) {
  let action;
  if (f.equipped) action = '<span class="ft-state">Aktiv ✓</span>';
  else if (f.owned) action = `<button class="btn btn-primary ft-btn" data-equip="${f.id}" data-var="${f.variant}">Anlegen</button>`;
  else if (f.locked) action = '<span class="ft-locked">🔥 ab 30-Tage-Streak</span>';
  else if (f.cost > 0) action = `<button class="btn btn-primary ft-btn" data-buy="${f.id}" ${SHOP.sparks >= f.cost ? '' : 'disabled'}>${f.cost} ✦</button>`;
  else action = '<span class="ft-locked">🎁 aus Kiste</span>';
  const tile = document.createElement('div');
  tile.className = 'frame-tile' + (f.equipped ? ' equipped' : '');
  tile.innerHTML =
    `<div class="hero ft-preview" data-frame="${f.variant}">
       <div class="hero-card">
         <div class="frame-corners" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
         <span class="ft-mini">${f.name}</span>
       </div>
     </div>
     <div class="ft-meta"><span class="ft-name">${f.name}</span>${action}</div>`;
  return tile;
}
function renderFramesPanel(panel) {
  panel.innerHTML = '<p class="muted small">Aus Kisten, Streaks, Level-Ups oder Sparks. Tippen = anlegen.</p><div class="frame-shop"></div>';
  const wrap = panel.querySelector('.frame-shop');
  const order = [], byCat = {};
  SHOP.frames.forEach(f => { (byCat[f.theme] = byCat[f.theme] || (order.push(f.theme), [])).push(f); });
  order.forEach(theme => {
    const h = document.createElement('div'); h.className = 'frame-cat'; h.textContent = FRAME_CAT[theme] || theme;
    wrap.appendChild(h);
    byCat[theme].forEach(f => wrap.appendChild(frameTile(f)));
  });
  wrap.querySelectorAll('[data-buy]').forEach(b => b.addEventListener('click', () => buyFrame(+b.dataset.buy)));
  wrap.querySelectorAll('[data-equip]').forEach(b => b.addEventListener('click', () => equipFrame(+b.dataset.equip, b.dataset.var)));
}
async function buyFrame(id) {
  try { const r = await api('buy.php', 'POST', { cosmetic_id: id }); $('#spark-count').textContent = r.sparks; await loadShop(); }
  catch (e) { alert(e.message); }
}
async function equipFrame(id, variant) {
  try { const r = await api('equip.php', 'POST', { cosmetic_id: id, kind: 'frame' }); applyFrame(r.frame || variant); await loadShop(); }
  catch (e) { alert(e.message); }
}
function renderShopPanel(panel) {
  panel.innerHTML = '<p class="muted small">Kisten geben zufällig Outfits & Rahmen der Kategorie — keine Duplikate.</p><div class="boxes-compact"></div>';
  const wrap = panel.querySelector('.boxes-compact');
  SHOP.boxes.forEach(box => {
    const pct = box.total ? Math.round(box.owned / box.total * 100) : 0;
    const el = document.createElement('button'); el.className = 'box-card-compact' + (box.complete ? ' complete' : '');
    el.innerHTML =
      `<img class="box-pochi" src="${box.complete ? box.img_open : box.img_closed}" alt="">
       <div class="bc-info"><div class="box-title">${box.name}</div>
         <div class="bc-bar"><span style="width:${pct}%"></span></div>
         <div class="box-progress">${box.owned}/${box.total}${box.complete ? ' · komplett ✓' : ''}</div></div>
       <div class="bc-price">${box.complete ? '✓' : box.cost + ' ✦'}</div>`;
    el.addEventListener('click', () => openBoxModal(box));
    wrap.appendChild(el);
  });
}
function openBoxModal(box) {
  MODAL_BOX = box;
  $('#bm-name').textContent = box.name;
  $('#bm-img').src = box.complete ? box.img_open : box.img_closed;
  const rates = SHOP.drop_rates;
  $('#bm-rates').innerHTML = Object.keys(rates).map(r => `<span class="rate" data-rarity="${r}">${RAR_LABEL[r]} ${rates[r]}%</span>`).join('');
  $('#bm-contents').innerHTML = box.contents.map(c => c.category === 'frame'
    ? `<div class="fprev ${c.owned ? 'owned' : ''}" data-rarity="${c.rarity}" title="${c.name}"><div class="hero" data-frame="${c.slug}"><div class="hero-card"></div></div></div>`
    : `<div class="prev ${c.owned ? 'owned' : ''}" data-rarity="${c.rarity}" title="${c.name}"><img src="${poseImg(c)}" alt="${c.name}"></div>`).join('');
  $('#bm-progress').textContent = `🔓 ${box.owned}/${box.total}`;
  const ob = $('#bm-open');
  // Hinweis: NICHT #bm-cost separat setzen — ob.innerHTML unten ersetzt den Button-
  // Inhalt (inkl. des bm-cost-Spans), sonst wäre er beim 2. Öffnen null → Crash.
  if (box.complete) { ob.textContent = 'Komplett ✓'; ob.disabled = true; }
  else { ob.innerHTML = `Öffnen · ${box.cost} ✦`; ob.disabled = SHOP.sparks < box.cost; }
  $('#box-modal').classList.remove('hidden');
}
function renderPlaceholder(panel, cat) {
  const P = {
    anim:  { ic: '✨', t: 'Animationen', d: 'Idle-, Feier-, Reise- & Special-Animationen — bald freischaltbar.', items: ['Idle', 'Feiern', 'Reise', 'Special'] },
    icons: { ic: '📱', t: 'App-Icons', d: 'Wechsle das Home-Bildschirm-Icon deiner App — bald freischaltbar.', items: ['Klassik', 'Gold', 'Sakura', 'Neon'] },
    items: { ic: '🎒', t: 'Items & Slots', d: 'Ausrüstungs-Slots — kommt mit „Tanuki Reisen" (v2).', slots: ['Accessoire', 'Reise-Item', 'Special'] },
  }[cat];
  if (!P) { panel.innerHTML = ''; return; }
  const body = P.slots
    ? '<div class="slot-grid">' + P.slots.map(s => `<div class="slot-tile"><span class="slot-ic">➕</span><span class="slot-nm">${s}</span><span class="slot-soon">leer</span></div>`).join('') + '</div>'
    : '<div class="slot-grid">' + P.items.map(s => `<div class="slot-tile"><span class="slot-nm">${s}</span><span class="slot-soon">bald</span></div>`).join('') + '</div>';
  panel.innerHTML = `<div class="cust-ph"><div class="ph-ic">${P.ic}</div><h3 class="h3">${P.t}</h3><p class="muted small">${P.d}</p>${body}</div>`;
}
async function equip(id) {
  try {
    const r = await api('equip.php', 'POST', { cosmetic_id: id });
    EQUIPPED = r.equipped; applyTanuki();
    await loadShop();
  } catch (e) { alert(e.message); }
}

/* ---------- Lootbox öffnen ---------- */
function openBox(box) {
  CURRENT_BOX = box;
  $('#lb-boxname').textContent = box.name;
  $('#lb-box-img').src = box.img_closed;
  $('#lb-cost').textContent = box.cost;
  $('#lb-closed').classList.remove('hidden');
  $('#lb-opening').classList.add('hidden');
  $('#lb-reveal').classList.add('hidden');
  $('#lootbox').classList.remove('hidden');
  playSound('sfx-chest-click');
}
async function doOpen() {
  if (!CURRENT_BOX) return;
  $('#lb-open-btn').disabled = true;
  try {
    const r = await api('openbox.php', 'POST', { box_id: CURRENT_BOX.id });
    $('#spark-count').textContent = r.sparks;
    $('#shop-sparks').textContent = r.sparks;
    // Aufreiß-Sequenz: zu → offen → Outfit
    $('#lb-closed').classList.add('hidden');
    $('#lb-reveal').classList.add('hidden');
    $('#lb-open-img').src = CURRENT_BOX.img_open;
    $('#lb-opening').classList.remove('hidden');
    playSound('sfx-stab');
    await new Promise(res => setTimeout(res, 850));
    $('#lb-opening').classList.add('hidden');
    revealItem(r);
  } catch (e) { alert(e.message); $('#lootbox').classList.add('hidden'); }
  finally { $('#lb-open-btn').disabled = false; }
}
function revealItem(r) {
  LAST_DRAW = r;
  const c = r.cosmetic;
  $('#lb-closed').classList.add('hidden');
  const rev = $('#lb-reveal'); rev.classList.remove('hidden'); rev.dataset.rarity = r.rarity;
  if (c.category === 'frame') {
    $('#lb-img').classList.add('hidden');
    const fp = $('#lb-frame'); fp.classList.remove('hidden'); fp.dataset.frame = c.slug;
  } else {
    $('#lb-frame').classList.add('hidden');
    $('#lb-img').classList.remove('hidden');
    $('#lb-img').src = c.poses.celebrate || c.poses.happy || Object.values(c.poses)[0];
  }
  $('#lb-rarity').textContent = RAR_LABEL[r.rarity];
  $('#lb-name').textContent = c.name;
  const info = $('#lb-dupe'); info.classList.remove('hidden');
  info.textContent = r.complete ? `Set komplett! 🎉 ${r.owned}/${r.total}` : `Neu! · ${r.owned}/${r.total} im Set`;
  $('#lb-equip').disabled = false; $('#lb-equip').textContent = 'Anlegen';
  // „Nochmal" nur wenn Set noch offen und genug Sparks
  const again = $('#lb-again');
  again.classList.toggle('hidden', r.complete);
  again.disabled = r.complete || r.sparks < CURRENT_BOX.cost;
  playSound('sfx-win');
  lbConfetti(r.rarity);
}
function initLootbox() {
  $('#lb-open-btn').addEventListener('click', doOpen);
  $('#lb-again').addEventListener('click', doOpen);
  $('#lb-equip').addEventListener('click', async () => {
    if (LAST_DRAW && !LAST_DRAW.duplicate) {
      const c = LAST_DRAW.cosmetic;
      if (c.category === 'frame') await equipFrame(c.id, c.slug);
      else await equip(c.id);
    }
    $('#lootbox').classList.add('hidden');
  });
  $('#lb-close').addEventListener('click', async () => {
    $('#lootbox').classList.add('hidden'); await loadShop();
  });
}
function lbConfetti(rarity) {
  const box = $('#lb-confetti'); box.innerHTML = '';
  const map = {
    gewoehnlich: ['#9AA0A6', '#C7CCD1'], selten: ['#3E9BE8', '#7FC0F0'],
    episch: ['#5F58E0', '#FF5C8A'], legendaer: ['#F5A623', '#FFD77A', '#FF5C8A'],
  };
  const cols = map[rarity] || map.gewoehnlich;
  const n = rarity === 'legendaer' ? 44 : rarity === 'episch' ? 30 : 18;
  for (let i = 0; i < n; i++) {
    const c = document.createElement('i');
    c.style.left = Math.random() * 100 + '%';
    c.style.background = cols[i % cols.length];
    c.style.animationDelay = (Math.random() * 0.25) + 's';
    box.appendChild(c);
  }
}

/* ---------- Web Push ---------- */
function urlB64ToUint8Array(base64) {
  const pad = '='.repeat((4 - base64.length % 4) % 4);
  const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(b64); const arr = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
  return arr;
}
let PUSH = null;
async function loadPush() {
  try { PUSH = await api('push.php'); } catch (_) { return; }
  const supported = ('serviceWorker' in navigator) && ('PushManager' in window);
  const enableBtn = $('#push-enable'), settings = $('#push-settings'), msg = $('#push-msg');
  if (!supported) {
    enableBtn.classList.add('hidden'); settings.classList.add('hidden');
    msg.textContent = 'Web-Push wird hier nicht unterstützt. Auf dem iPhone die App zum Home-Bildschirm hinzufügen und dort aktivieren.';
    return;
  }
  msg.textContent = '';
  const wins = (PUSH.prefs && PUSH.prefs.windows) || [];
  $$('#push-windows .pill').forEach(p => p.classList.toggle('is-active', wins.includes(p.dataset.win)));
  enableBtn.classList.toggle('hidden', PUSH.subscribed);
  settings.classList.toggle('hidden', !PUSH.subscribed);
}
async function enablePush() {
  const msg = $('#push-msg');
  try {
    if (Notification.permission === 'denied') { msg.textContent = 'Benachrichtigungen sind blockiert — in den Einstellungen erlauben.'; return; }
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') { msg.textContent = 'Ohne Erlaubnis keine Erinnerungen.'; return; }
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(PUSH.public_key) });
    await api('push.php', 'POST', { action: 'subscribe', subscription: sub.toJSON() });
    PUSH.subscribed = true;
    $('#push-enable').classList.add('hidden'); $('#push-settings').classList.remove('hidden');
    msg.textContent = 'Aktiviert! ✅';
  } catch (e) { msg.textContent = 'Konnte nicht aktivieren: ' + e.message; }
}
async function savePush() {
  const windows = $$('#push-windows .pill.is-active').map(p => p.dataset.win);
  try { await api('push.php', 'POST', { action: 'prefs', enabled: true, windows }); $('#push-msg').textContent = 'Gespeichert. ⏰'; }
  catch (e) { $('#push-msg').textContent = e.message; }
}
function initPush() {
  $('#push-enable').addEventListener('click', enablePush);
  $('#push-save').addEventListener('click', savePush);
  $('#push-test').addEventListener('click', async () => {
    try { const r = await api('push.php', 'POST', { action: 'test' }); $('#push-msg').textContent = r.ok ? 'Test gesendet 📨' : 'Kein Versand — Subscription aktiv?'; }
    catch (e) { $('#push-msg').textContent = e.message; }
  });
  $$('#push-windows .pill').forEach(p => p.addEventListener('click', () => p.classList.toggle('is-active')));
}

/* ---------- Aufgaben bearbeiten ---------- */
let TASKS = [], EDIT_ID = null;

async function loadTasks() {
  try { TASKS = (await api('tasks.php')).tasks || []; renderTaskList(); } catch (_) {}
}
function renderTaskList() {
  const wrap = $('#task-list'); wrap.innerHTML = '';
  $('#task-empty').classList.toggle('hidden', TASKS.length > 0);
  applyFirstRun(TASKS.length > 0);
  TASKS.forEach(t => {
    const meta = t.type === 'termin' ? 'Termin' : `${t.time_estimate}m · ${t.energy}`;
    const row = document.createElement('button');
    row.className = 'task-row';
    row.innerHTML =
      `<span class="dom-dot" style="background:${DOMAIN_COLOR[t.domain] || 'var(--dom-privat)'}"></span>
       <span class="task-row-title">${t.title}${t.recurrence_rule ? ' 🔁' : ''}</span>
       <span class="task-row-meta">${meta}</span><span class="task-row-chev">›</span>`;
    row.addEventListener('click', () => openTaskEdit(t));
    wrap.appendChild(row);
  });
}
function setPills(groupId, val) {
  $$('#' + groupId + ' .pill').forEach(p => p.classList.toggle('is-active', String(p.dataset.val) === String(val)));
}
// Vorhandene KI-Schätzung auf 10/30/60 (wie im Home) runden
function snapTime(m) {
  return [10, 30, 60].reduce((a, b) => Math.abs(b - m) < Math.abs(a - m) ? b : a);
}
function openTaskEdit(t) {
  EDIT_ID = t.id;
  $('#te-title').value = t.title || '';
  $('#te-notes').value = t.notes || '';
  setPills('te-time', snapTime(+t.time_estimate || 30));
  setPills('te-energy', t.energy);
  setPills('te-domain', t.domain);
  setPills('te-priority', t.priority);
  $('#te-msg').textContent = '';
  $('#task-sheet').classList.remove('hidden');
}
function openTaskEditById(id) {
  const t = TASKS.find(x => +x.id === +id);
  if (t) openTaskEdit(t);
}
function closeTaskSheet() { $('#task-sheet').classList.add('hidden'); EDIT_ID = null; }

async function saveTask() {
  if (!EDIT_ID) return;
  const body = { id: EDIT_ID, title: $('#te-title').value.trim(), notes: $('#te-notes').value };
  if (!body.title) { $('#te-msg').textContent = 'Titel darf nicht leer sein.'; return; }
  const t = $('#te-time .pill.is-active'); if (t) body.time_estimate = +t.dataset.val;
  const e = $('#te-energy .pill.is-active'); if (e) body.energy = e.dataset.val;
  const d = $('#te-domain .pill.is-active'); if (d) body.domain = d.dataset.val;
  const p = $('#te-priority .pill.is-active'); if (p) body.priority = +p.dataset.val;
  try { await api('tasks.php', 'POST', body); closeTaskSheet(); await loadTasks(); loadWeek(); }
  catch (err) { $('#te-msg').textContent = err.message; }
}
async function deleteTask() {
  if (!EDIT_ID) return;
  if (!confirm('Aufgabe wirklich löschen?')) return;
  try { await api('tasks.php', 'POST', { id: EDIT_ID, action: 'delete' }); closeTaskSheet(); await loadTasks(); loadWeek(); }
  catch (err) { $('#te-msg').textContent = err.message; }
}
function initTasks() {
  $('#task-close').addEventListener('click', closeTaskSheet);
  $('#task-sheet').addEventListener('click', e => { if (e.target.id === 'task-sheet') closeTaskSheet(); });
  $('#te-save').addEventListener('click', saveTask);
  $('#te-delete').addEventListener('click', deleteTask);
  ['te-time', 'te-energy', 'te-domain', 'te-priority'].forEach(g => {
    $('#' + g).addEventListener('click', e => {
      const p = e.target.closest('.pill'); if (!p) return;
      $$('#' + g + ' .pill').forEach(x => x.classList.toggle('is-active', x === p));
    });
  });
}

/* ---------- Tanuki's Adventures (Reise) ---------- */
let JNY = null;
const JNY_FEED_ICON = { waypoint: '📍', statue: '🗿', monk: '🚶', hidden: '✨', arrival: '🏁', lootbox: '🎁' };
// 'xp'-Items heißen bewusst „Antrieb" — sie schieben nur die Reise-Distanz,
// nie die echten XP (eiserne Regel, journey.md §0/§3).
const JNY_TYPE = { speed: { ico: '💨', label: 'Tempo' }, loot: { ico: '🍀', label: 'Glück' }, xp: { ico: '✨', label: 'Antrieb' } };

// km hübsch deutsch: 1 Nachkommastelle, Komma.
function jnyKm(km) {
  return (+km || 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}
function jnyAttr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;'); }

async function loadJourney() {
  const msg = $('#journey-msg');
  try {
    const s = await api('journey.php');
    msg.classList.add('hidden');
    renderJourney(s);
    jnyHandleEvents(s.new_events || []);
    // Splash nur beim allerersten Öffnen — mit dem ausgerüsteten Skin, nicht dem Basis-Tanuki
    if (!localStorage.getItem('taskly-journey-seen')) {
      const si = $('#jny-splash-img'); if (si) si.src = tanukiFor('happy');
      $('#journey-splash').classList.remove('hidden');
    }
  } catch (e) {
    // 403 (Level) / 404 (Feature aus) / Netz → freundlicher Hinweis statt leerer View
    ['journey-active', 'journey-pick', 'jny-gear', 'jny-reveal', 'journey-splash']
      .forEach(id => $('#' + id).classList.add('hidden'));
    msg.innerHTML = '<p class="muted">🦝 Die große Reise ist noch nicht offen — mach in Ruhe weiter deine Aufgaben, dein Tanuki packt schon mal den Rucksack.</p>';
    msg.classList.remove('hidden');
  }
}

function renderJourney(s) {
  JNY = s;
  const j = s.journey && s.journey.status === 'active' ? s.journey : null;
  $('#journey-active').classList.toggle('hidden', !j);
  $('#journey-pick').classList.toggle('hidden', !!j);
  $('#jny-gear').classList.remove('hidden');

  if (j) {
    $('#jny-scene').dataset.theme = j.destination;
    $('#jny-dest').textContent = j.name;
    $('#jny-remaining').textContent = `noch ${jnyKm(j.remaining_km)} km`;
    renderJnyPath(j, s.waypoints || []);
    // Boost-Badge nur bei aktivem Schub
    const boostOn = j.boost_pct > 0 && j.boost_until && new Date(String(j.boost_until).replace(' ', 'T')) > new Date();
    const bb = $('#jny-boost');
    bb.classList.toggle('hidden', !boostOn);
    if (boostOn) bb.textContent = `🔥 Rückenwind +${j.boost_pct} %`;
  }

  // Ausdauer: Meter → km
  const pct = s.stamina_max_m ? Math.max(0, Math.min(1, s.stamina_m / s.stamina_max_m)) : 0;
  $('#jny-stamina-fill').style.transform = `scaleX(${pct})`;
  $('#jny-stamina-label').textContent = `${jnyKm(s.stamina_m / 1000)} / ${Math.round((s.stamina_max_m || 0) / 1000)} km`;

  // Boni-Chips
  const bo = s.bonuses || {};
  $('#jny-bonuses').innerHTML = ['speed', 'loot', 'xp'].map(k =>
    `<span class="chip">${JNY_TYPE[k].ico} +${Math.round((bo[k] || 0) * 100)} % ${JNY_TYPE[k].label}</span>`).join('');

  // Verborgene Events
  const hh = $('#jny-hidden');
  if (j && s.hidden_events_left > 0) {
    hh.textContent = s.hidden_events_left === 1
      ? '❓ 1 verborgenes Event wartet noch auf dieser Strecke.'
      : `❓ ${s.hidden_events_left} verborgene Events warten noch auf dieser Strecke.`;
    hh.classList.remove('hidden');
  } else hh.classList.add('hidden');

  // Feed (letzte Entdeckungen)
  const rows = (s.feed || []).map(f => {
    const ico = JNY_FEED_ICON[f.kind] || '✨';
    const km = f.at_km != null ? `km ${f.at_km}` : '';
    const extra = f.reward_type === 'sparks' && f.reward_amount ? `+${f.reward_amount} ✦`
      : f.reward_type === 'item' ? 'Item 🎒'
      : f.reward_type === 'lootbox' ? 'Kiste 🎁'
      : f.reward_type === 'distance' && f.reward_amount ? `+${jnyKm(f.reward_amount / 1000)} km` : '';
    return `<div class="jny-feed-row"><span class="jny-feed-ico">${ico}</span>
        <span class="jny-feed-label">${f.label}</span>
        <span class="jny-feed-meta">${[km, extra].filter(Boolean).join(' · ')}</span></div>`;
  });
  $('#jny-feed').innerHTML = rows.length ? rows.join('')
    : '<p class="muted small">Noch nichts entdeckt — die ersten Schritte kommen mit deiner nächsten Aufgabe. 🍃</p>';

  // Ziel-Karten
  $('#jny-destinations').innerHTML = (s.destinations || []).map(d => {
    const badge = d.done ? '<span class="jny-dest-badge">✓ besucht</span>' : '';
    const lock = d.unlocked ? '' : `<span class="jny-dest-lock">🔒 ab Level ${d.unlock_level}</span>`;
    return `<button class="jny-dest-card${d.unlocked ? '' : ' locked'}" data-dest="${jnyAttr(d.destination)}" data-theme="${jnyAttr(d.destination)}"${d.unlocked ? '' : ' disabled'}>
        <span class="jny-dest-art" aria-hidden="true"></span>
        <span class="jny-dest-body">
          <span class="jny-dest-name">${d.name}</span>
          ${d.tagline ? `<span class="jny-dest-tag">${d.tagline}</span>` : ''}
          <span class="jny-dest-meta">${d.total_km} km ${lock} ${badge}</span>
        </span>
      </button>`;
  }).join('');

  // Ausrüstung
  const items = s.items || [];
  const eq = items.filter(i => i.equipped).length;
  $('#jny-gear-count').textContent = `${eq}/${s.equip_slots || 3}`;
  $('#jny-items-empty').classList.toggle('hidden', items.length > 0);
  $('#jny-items').innerHTML = items.map(i => {
    const t = JNY_TYPE[i.type] || { ico: '🎒' };
    return `<button class="jny-item${i.equipped ? ' equipped' : ''}" data-id="${i.id}" data-rarity="${jnyAttr(i.rarity)}" title="${jnyAttr(i.flavor)}">
        ${i.equipped ? '<span class="jny-item-eq">●</span>' : ''}
        <span class="jny-item-ico">${t.ico}</span>
        <span class="jny-item-name">${i.name}</span>
        <span class="jny-item-val">+${Math.round((i.value || 0) * 100)} %</span>
      </button>`;
  }).join('');
}

// Fortschritts-Pfad: Linie, Wegpunkt-Knoten, Tanuki-Marker (transform-positioniert)
function renderJnyPath(j, wps) {
  const nodes = wps.map(w => {
    const left = j.total_km ? Math.min(100, w.at_km / j.total_km * 100) : 0;
    const goal = w.at_km >= j.total_km;
    const cls = 'jny-node' + (goal ? ' goal' : '') + (w.claimed ? ' claimed' : '');
    const inner = goal ? '🏁' : (w.claimed ? '✓' : '');
    return `<span class="${cls}" style="left:${left}%" title="${jnyAttr(w.flavor || w.name)}">${inner}</span>`;
  }).join('');
  const done = Math.min(100, Math.max(0, +j.pct || 0));
  // Echter Tanuki statt Emoji: ausgerüsteter Skin (tanukiFor) + Stimmung —
  // unterwegs = happy, Ausdauer leer = müde (Rast), angekommen = celebrate.
  const resting = !JNY || (+JNY.stamina_m || 0) <= 0;
  const pose = (done >= 100 || j.status === 'done') ? 'celebrate' : (resting ? 'tired' : 'happy');
  const hint = pose === 'celebrate' ? 'Angekommen!' : (resting ? 'Macht Rast — eine Aufgabe gibt neue Ausdauer.' : 'Unterwegs!');
  $('#jny-path').innerHTML =
    `<span class="jny-line"></span><span class="jny-line-done" style="width:${done}%"></span>` +
    nodes +
    `<span class="jny-tanuki" style="left:${done}%" title="${jnyAttr(hint)}"><img class="jny-tanuki-bob jny-tanuki-img" src="${tanukiFor(pose)}" alt=""></span>`;
}

// Neue Tick-Events: Reveal-Panel für gezogene Items/Kisten, Fanfare bei Ankunft
function jnyHandleEvents(events) {
  if (!events || !events.length) return;
  const rewards = [];
  let arrived = false;
  events.forEach(ev => {
    if (ev.kind === 'arrival') arrived = true;
    const list = Array.isArray(ev.reward) ? ev.reward : (ev.reward ? [ev.reward] : []);
    list.forEach(r => rewards.push(r));
  });
  if (rewards.length) {
    const html = rewards.map(r => {
      if (r.fallback_sparks) {
        return `<div class="jny-rv-item"><span class="jny-rv-ico">✦</span><div><b>+${r.fallback_sparks} Sparks</b>
            <span class="jny-rv-flavor">Schon alles gesammelt — dafür klimpert jetzt der Beutel.</span></div></div>`;
      }
      if (r.cosmetic) {
        return `<div class="jny-rv-item" data-rarity="${jnyAttr(r.rarity)}"><span class="jny-rv-ico">🎁</span><div><b>${r.cosmetic.name}</b>
            <span class="jny-rv-rar">${RAR_LABEL[r.rarity] || r.rarity}</span>
            <span class="jny-rv-flavor">Frisch aus der Kiste — schau im Tanuki-Tab vorbei.</span></div></div>`;
      }
      const t = JNY_TYPE[r.type] || { ico: '🎒' };
      return `<div class="jny-rv-item" data-rarity="${jnyAttr(r.rarity)}"><span class="jny-rv-ico">${t.ico}</span><div><b>${r.name}</b>
          <span class="jny-rv-rar">${RAR_LABEL[r.rarity] || r.rarity} · +${Math.round((r.value || 0) * 100)} %</span>
          ${r.flavor ? `<span class="jny-rv-flavor">${r.flavor}</span>` : ''}</div></div>`;
    }).join('');
    const rv = $('#jny-reveal');
    rv.innerHTML = `<div class="jny-rv-head">Unterwegs gefunden ✨</div>${html}<button class="btn btn-ghost jny-rv-close" id="jny-rv-close">Schön!</button>`;
    rv.classList.remove('hidden');
    if (typeof playSound === 'function') playSound('sfx-win');
  }
  if (arrived) {
    if (typeof playSound === 'function') playSound('sfx-levelup');
    if (typeof confetti === 'function') confetti();
    showToast('Angekommen! Dein Tanuki ruht sich kurz aus. 🏁');
  }
}

function initJourney() {
  $('#journey-splash-go').addEventListener('click', () => {
    localStorage.setItem('taskly-journey-seen', '1');
    $('#journey-splash').classList.add('hidden');
  });
  // Reveal-Schließen per Delegation (Button entsteht erst beim Render)
  $('#jny-reveal').addEventListener('click', e => {
    if (e.target.closest('.jny-rv-close')) $('#jny-reveal').classList.add('hidden');
  });
  // Ziel wählen → Reise startet direkt (kein confirm)
  $('#jny-destinations').addEventListener('click', async e => {
    const card = e.target.closest('.jny-dest-card[data-dest]');
    if (!card || card.classList.contains('locked')) return;
    card.disabled = true;
    try {
      const r = await api('journey.php', 'POST', { action: 'start', destination: card.dataset.dest });
      renderJourney(r.state);
      showToast('Die Reise beginnt — gute Pfade! 🦝');
    } catch (err) { showToast(err.message); card.disabled = false; }
  });
  // Item an-/ablegen: kein optimistisches Toggle, Server-State gewinnt
  $('#jny-items').addEventListener('click', async e => {
    const tile = e.target.closest('.jny-item[data-id]');
    if (!tile || tile.classList.contains('is-busy')) return;
    tile.classList.add('is-busy');
    const equipped = tile.classList.contains('equipped');
    try {
      const r = await api('journey.php', 'POST', { action: equipped ? 'unequip' : 'equip', item_id: +tile.dataset.id });
      renderJourney(r.state);
    } catch (err) { showToast(err.message); tile.classList.remove('is-busy'); }
  });
}

/* ---------- Boot ---------- */
async function boot() {
  try {
    const s = await api('state.php');
    if (s.logged_in) {
      if (s.journey_cfg) {
        GATES = {
          enabled: !!s.journey_cfg.enabled,
          advLevel: +s.journey_cfg.unlock_level || 3,
          shopLevel: +s.journey_cfg.shop_unlock_level || 4,
        };
      }
      renderProgress(s.progress);
      updateJourneyLine(s.journey);
      $('#journey-line')?.addEventListener('click', () => showView('journey'));
      showIce(s);
      EQUIPPED = s.equipped || null; applyTanuki();
      applyFrame(s.frame);
      applyFirstRun(!!s.has_tasks);
      showApp(); resetHero(); showView('today');
    } else showAuth();
  } catch (_) { showAuth(); }
}

initTheme(); initSound(); initAuth(); initDump(); initQuick(); initHero(); initMore(); initNav(); initLootbox(); initPlan(); initPush(); initTasks(); initCust(); initJourney();
boot();

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}
