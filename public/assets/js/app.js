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

/* ---------- State / Header ---------- */
function renderProgress(p) {
  if (!p) return;
  $('#xp-level').textContent = p.level;
  const pct = p.xp_needed ? Math.round((p.xp_into / p.xp_needed) * 100) : 0;
  $('#xp-ring').style.setProperty('--p', pct);
  $('#spark-count').textContent = p.sparks;
  $('#streak-count').textContent = p.streak;
  const flame = $('.streak-flame'); if (flame) flame.textContent = p.frozen ? '🧊' : '🔥';
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
  $('#braindump-wrap').classList.toggle('hidden', !hasTasks);
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
  $('#dump-trigger').addEventListener('click', openDump);
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
      setTimeout(closeDump, 1000);
    } catch (err) { $('#dump-result').textContent = err.message; }
    finally { btn.disabled = false; btn.textContent = 'Erfassen'; }
  });

  // Voice als Progressive Enhancement (architecture.md §4.0)
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SR) {
    const vbtn = $('#dump-voice'); vbtn.hidden = false;
    const rec = new SR(); rec.lang = 'de-DE'; rec.interimResults = false;
    vbtn.addEventListener('click', () => { try { rec.start(); vbtn.textContent = '… hört zu'; } catch (_) {} });
    rec.onresult = e => {
      const t = [...e.results].map(r => r[0].transcript).join(' ');
      const ta = $('#dump-text'); ta.value = (ta.value ? ta.value + ' ' : '') + t;
    };
    rec.onend = () => { vbtn.textContent = '🎙️ Sprechen'; };
    rec.onerror = () => { vbtn.textContent = '🎙️ Sprechen'; };
  }
}

/* ---------- Quick-Selects ---------- */
function initQuick() {
  $$('.quick-group').forEach(group => {
    group.addEventListener('click', e => {
      const pill = e.target.closest('.pill');
      if (!pill) return;
      $$('.pill', group).forEach(p => p.classList.toggle('is-active', p === pill));
      updateWhatnow();
    });
  });
}
// „Was jetzt?" erst aktiv, wenn Zeit UND Akku gewählt sind (neutraler Start).
function updateWhatnow() {
  const ready = $('#q-time .pill.is-active') && $('#q-energy .pill.is-active');
  $('#btn-whatnow').disabled = !ready;
  const hint = $('#hero-hint');
  if (hint) hint.textContent = ready ? 'Eine Sache. Kein Stress.' : 'Zeit & Akku wählen, dann los.';
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
  updateWhatnow();
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
  $('#pick-reason').textContent = r.reason || '';
  $('#pick-title').textContent = p.title;
  $('#pick-meta').innerHTML =
    `<span class="chip"><span class="dot" style="background:${DOMAIN_COLOR[p.domain] || 'var(--dom-privat)'}"></span>${p.domain}</span>` +
    `<span class="chip">⏱ ${p.time_estimate} Min</span>` +
    `<span class="chip">🔋 ${p.energy}</span>` +
    (p.base_xp ? `<span class="chip chip-xp">+${p.base_xp} XP</span>` : '');
  $('#hero-empty').classList.add('hidden');
  $('#hero-pick').classList.remove('hidden');
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
  if (rw.thawed) {
    const d = document.createElement('div');
    d.className = 'reward-badge'; d.textContent = 'Streak gerettet! 🔥 Weiter geht’s.';
    extra.appendChild(d);
  }
  if (rw.leveled_up) {
    const d = document.createElement('div');
    d.className = 'reward-badge'; d.textContent = rw.level_message || `Level ${rw.level}! 🎉`;
    extra.appendChild(d);
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
}

// Tanuki-Mimik passend zur gewählten Energie (im Vorschlag-Zustand)
const MOOD_BY_ENERGY = { 'müde': 'tired', 'mude': 'tired', 'ok': 'neutral', 'voll': 'celebrate' };

// Antippen → kleiner Hüpfer + Spruch (Persönlichkeit, brand.md §4)
const TANUKI_LINES = [
  'Komm, das kriegst du. 💪',
  'Eine Sache nach der anderen.',
  'Schön, dass du da bist. 🍵',
  'Kein Stress — Schritt für Schritt.',
  'Bereit, wenn du’s bist.',
  'Klein anfangen zählt auch.',
];
let _bubbleTimer = null;
function pokeTanuki() {
  const img = $('#hero-tanuki');
  img.classList.remove('tanuki-poke'); void img.offsetWidth; img.classList.add('tanuki-poke');
  const b = $('#tanuki-bubble');
  b.textContent = TANUKI_LINES[Math.floor(Math.random() * TANUKI_LINES.length)];
  b.classList.add('show');
  clearTimeout(_bubbleTimer);
  _bubbleTimer = setTimeout(() => b.classList.remove('show'), 2200);
}

/* ---------- Views / Nav ---------- */
function showView(which) {
  ['today', 'plan', 'shop', 'more'].forEach(v => {
    $('#view-' + v).classList.toggle('hidden', which !== v);
    $('#nav-' + v).classList.toggle('is-active', which === v);
  });
  if (which === 'shop') loadShop();
  if (which === 'plan') loadWeek();
  if (which === 'more') loadMore();
}
function initNav() {
  ['today', 'plan', 'shop', 'more'].forEach(v =>
    $('#nav-' + v).addEventListener('click', () => showView(v)));
}

/* ---------- Wochen-Plan ---------- */
function renderWeek(days) {
  const w = $('#week'); w.innerHTML = '';
  days.forEach(day => {
    const col = document.createElement('div');
    col.className = 'day' + (day.is_today ? ' today' : '');
    const dd = +day.date.slice(8, 10), mm = +day.date.slice(5, 7);
    const head = `<div class="day-head"><span class="day-wd">${day.weekday}</span>`
      + `<span class="day-date">${dd}.${mm}.</span>`
      + (day.is_today ? '<span class="day-badge">heute</span>' : '') + '</div>';
    let body;
    if (!day.tasks.length) {
      body = '<div class="day-empty">frei 🍵</div>';
    } else {
      body = day.tasks.map(t => {
        const time = (t.type === 'termin' && t.fixed_at) ? t.fixed_at.slice(11, 16) + ' · ' : '';
        const meta = t.type === 'termin' ? 'Termin' : (t.time_estimate + 'm · ' + t.base_xp + ' XP');
        return `<div class="plan-card${t.done ? ' done' : ''}" data-type="${t.type}" data-task="${t.task_id}">
            <span class="dom-dot" style="background:${DOMAIN_COLOR[t.domain] || 'var(--dom-privat)'}"></span>
            <span class="plan-title">${time}${t.title}${t.recurring ? ' 🔁' : ''}</span>
            <span class="plan-meta">${meta}</span>
          </div>`;
      }).join('');
    }
    col.innerHTML = head + `<div class="day-body">${body}</div>`;
    w.appendChild(col);
  });
}
async function loadWeek() {
  try { renderWeek((await api('week.php')).days); loadTasks(); }
  catch (e) { alert(e.message); }
}
function initPlan() {
  $('#week').addEventListener('click', e => {
    const c = e.target.closest('.plan-card[data-task]');
    if (c) openTaskEditById(+c.dataset.task);
  });
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
  try { SHOP = await api('shop.php'); renderShop(); }
  catch (e) { alert(e.message); }
}
function renderShop() {
  $('#shop-sparks').textContent = SHOP.sparks;
  const wrap = $('#boxes'); wrap.innerHTML = '';
  SHOP.boxes.forEach(box => {
    const complete = box.complete;
    const can = !complete && SHOP.sparks >= box.cost;
    const rates = SHOP.drop_rates;
    const rateChips = Object.keys(rates).map(r =>
      `<span class="rate" data-rarity="${r}">${RAR_LABEL[r]} ${rates[r]}%</span>`).join('');
    const prev = box.contents.map(c =>
      `<div class="prev ${c.owned ? 'owned' : ''}" data-rarity="${c.rarity}"><img src="${poseImg(c)}" alt="${c.name}" title="${c.name} · ${RAR_LABEL[c.rarity]}"></div>`).join('');
    const head = complete
      ? `<div class="box-complete">100% freigeschaltet ✓</div>`
      : `<div class="box-rates">${rateChips}</div>`;
    const btn = complete
      ? `<span class="box-done">Komplett ✓</span>`
      : `<button class="btn btn-primary" ${can ? '' : 'disabled'}>Öffnen</button>`;
    const el = document.createElement('div'); el.className = 'box-card' + (complete ? ' complete' : '');
    el.innerHTML =
      `<div class="box-top"><img class="box-pochi" src="${complete ? box.img_open : box.img_closed}" alt="">
         <div><div class="box-title">${box.name}</div>${head}
           <div class="box-progress">🔓 ${box.owned}/${box.total} freigeschaltet</div></div></div>
       <div class="box-preview">${prev}</div>
       <div class="box-buy"><span class="box-cost">${box.cost} ✦</span>${btn}</div>`;
    if (!complete) el.querySelector('button').addEventListener('click', () => openBox(box));
    wrap.appendChild(el);
  });
  renderGarderobe();
  renderFrames();
}
const FRAME_CAT = { basis: 'Standard', prestige: 'Prestige', japan: 'Japanisch', helden: 'Helden', cyberpunk: 'Cyberpunk', steampunk: 'Steampunk', blumen: 'Blumen' };
function frameTile(f) {
  let action;
  if (f.equipped) action = '<span class="ft-state">Aktiv ✓</span>';
  else if (f.owned) action = `<button class="btn btn-primary ft-btn" data-equip="${f.id}" data-var="${f.variant}">Anlegen</button>`;
  else action = `<button class="btn btn-primary ft-btn" data-buy="${f.id}" ${SHOP.sparks >= f.cost ? '' : 'disabled'}>${f.cost} ✦</button>`;
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
function renderFrames() {
  const wrap = $('#frame-shop'); if (!wrap || !SHOP.frames) return;
  wrap.innerHTML = '';
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
function renderGarderobe() {
  const g = $('#garderobe'); g.innerHTML = '';
  $('#garderobe-empty').classList.toggle('hidden', SHOP.inventory.length > 0);
  const base = document.createElement('div');
  base.className = 'outfit-tile base' + (SHOP.equipped_id ? '' : ' equipped');
  base.innerHTML = `<img src="/assets/img/tanuki/base-neutral.png" alt="Basis"><div class="ot-name">Basis</div>`
    + (SHOP.equipped_id ? '' : '<span class="ot-eq">●</span>');
  base.addEventListener('click', () => equip(0));
  g.appendChild(base);
  SHOP.inventory.forEach(it => {
    const t = document.createElement('div');
    t.className = 'outfit-tile' + (it.equipped ? ' equipped' : '');
    t.dataset.rarity = it.rarity;
    t.innerHTML = `<img src="${poseImg(it)}" alt="${it.name}"><div class="ot-name">${it.name}</div>`
      + (it.equipped ? '<span class="ot-eq">●</span>' : '');
    t.addEventListener('click', () => equip(it.id));
    g.appendChild(t);
  });
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
  $('#lb-img').src = c.poses.celebrate || c.poses.happy || Object.values(c.poses)[0];
  $('#lb-rarity').textContent = RAR_LABEL[r.rarity];
  $('#lb-name').textContent = c.name;
  const info = $('#lb-dupe'); info.classList.remove('hidden');
  info.textContent = r.complete ? `Set komplett! 🎉 ${r.owned}/${r.total}` : `Neu! · ${r.owned}/${r.total} im Set`;
  $('#lb-equip').disabled = false; $('#lb-equip').textContent = 'Anlegen';
  // „Nochmal" nur wenn Set noch offen und genug Sparks
  const again = $('#lb-again');
  again.classList.toggle('hidden', r.complete);
  again.disabled = r.complete || r.sparks < CURRENT_BOX.cost;
  lbConfetti(r.rarity);
}
function initLootbox() {
  $('#lb-open-btn').addEventListener('click', doOpen);
  $('#lb-again').addEventListener('click', doOpen);
  $('#lb-equip').addEventListener('click', async () => {
    if (LAST_DRAW && !LAST_DRAW.duplicate) await equip(LAST_DRAW.cosmetic.id);
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

/* ---------- Boot ---------- */
async function boot() {
  try {
    const s = await api('state.php');
    if (s.logged_in) {
      renderProgress(s.progress);
      showIce(s);
      EQUIPPED = s.equipped || null; applyTanuki();
      applyFrame(s.frame);
      applyFirstRun(!!s.has_tasks);
      showApp(); resetHero(); showView('today');
    } else showAuth();
  } catch (_) { showAuth(); }
}

initTheme(); initAuth(); initDump(); initQuick(); initHero(); initMore(); initNav(); initLootbox(); initPlan(); initPush(); initTasks();
boot();

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}
