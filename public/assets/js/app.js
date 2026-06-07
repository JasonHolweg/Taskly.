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
function initTheme() {
  const saved = localStorage.getItem('taskly-theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
  $('#theme-toggle')?.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme');
    const next = cur === 'dark' ? 'light'
      : cur === 'light' ? ''
      : (matchMedia('(prefers-color-scheme: dark)').matches ? 'light' : 'dark');
    if (next) document.documentElement.setAttribute('data-theme', next);
    else document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('taskly-theme', next);
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
function initDump() {
  $('#dump-save').addEventListener('click', async () => {
    const text = $('#dump-text').value.trim();
    if (!text) return;
    const btn = $('#dump-save');
    btn.disabled = true; btn.textContent = 'Sortiere…';
    try {
      const r = await api('braindump.php', 'POST', { text });
      $('#dump-text').value = '';
      const word = r.count === 1 ? 'Aufgabe' : 'Aufgaben';
      $('#dump-result').textContent = `${r.count} ${word} erfasst${r.ai_used ? '' : ' (ohne KI)'}. Tipp „Was jetzt?".`;
      resetHero();
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
    });
  });
}
function ctx() {
  const time = +$('#q-time .pill.is-active').dataset.val;
  const energy = $('#q-energy .pill.is-active').dataset.val;
  return { time, energy };
}

/* ---------- Was jetzt? ---------- */
function resetHero() {
  $('#hero-pick').classList.add('hidden');
  $('#hero-empty').classList.add('hidden');
  $('#hero-start').classList.remove('hidden');
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

/* ---------- More ---------- */
function initMore() {
  $('#nav-more').addEventListener('click', async () => {
    if (confirm('Abmelden?')) { await api('auth/logout.php', 'POST').catch(() => {}); showAuth(); }
  });
  $$('[data-soon]').forEach(b => b.addEventListener('click', () => alert('Kommt bald ✨')));
}

/* ---------- Tanuki (equipped Outfit) ---------- */
const BASE_TANUKI = {
  neutral: '/assets/img/tanuki/base-neutral.png',
  happy: '/assets/img/tanuki/base-happy.png',
  celebrate: '/assets/img/tanuki/base-celebrate.png',
  tired: '/assets/img/tanuki/base-tired.png',
  sad: '/assets/img/tanuki/base-sad.png',
};
let EQUIPPED = null;
function tanukiFor(emotion) {
  if (EQUIPPED && EQUIPPED.poses) {
    if (emotion === 'neutral' && EQUIPPED.poses.happy) return EQUIPPED.poses.happy;
    if (EQUIPPED.poses[emotion]) return EQUIPPED.poses[emotion];
  }
  return BASE_TANUKI[emotion] || BASE_TANUKI.neutral;
}
function applyTanuki() {
  $$('#hero-start .tanuki, #hero-pick .tanuki').forEach(i => i.src = tanukiFor('neutral'));
  const empty = $('#hero-empty .tanuki'); if (empty) empty.src = tanukiFor('tired');
  const rt = $('#reward-tanuki'); if (rt) rt.src = tanukiFor('celebrate');
}

/* ---------- Views / Nav ---------- */
function showView(which) {
  $('#view-today').classList.toggle('hidden', which !== 'today');
  $('#view-shop').classList.toggle('hidden', which !== 'shop');
  $('#nav-today').classList.toggle('is-active', which === 'today');
  $('#nav-shop').classList.toggle('is-active', which === 'shop');
  if (which === 'shop') loadShop();
}
function initNav() {
  $('#nav-today').addEventListener('click', () => showView('today'));
  $('#nav-shop').addEventListener('click', () => showView('shop'));
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
    const can = SHOP.sparks >= box.cost;
    const rates = SHOP.drop_rates;
    const rateChips = Object.keys(rates).map(r =>
      `<span class="rate" data-rarity="${r}">${RAR_LABEL[r]} ${rates[r]}%</span>`).join('');
    const prev = box.contents.map(c =>
      `<div class="prev ${c.owned ? 'owned' : ''}" data-rarity="${c.rarity}"><img src="${poseImg(c)}" alt="${c.name}" title="${c.name} · ${RAR_LABEL[c.rarity]}"></div>`).join('');
    const el = document.createElement('div'); el.className = 'box-card';
    el.innerHTML =
      `<div class="box-top"><img class="box-pochi" src="${box.img_closed}" alt="">
         <div><div class="box-title">${box.name}</div><div class="box-rates">${rateChips}</div></div></div>
       <div class="box-preview">${prev}</div>
       <div class="box-buy"><span class="box-cost">${box.cost} ✦</span>
         <button class="btn btn-primary" ${can ? '' : 'disabled'}>Öffnen</button></div>`;
    el.querySelector('button').addEventListener('click', () => openBox(box));
    wrap.appendChild(el);
  });
  renderGarderobe();
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
  const dupe = $('#lb-dupe');
  if (r.duplicate) {
    dupe.textContent = `Schon dabei — +${r.refund} ✦ zurück`; dupe.classList.remove('hidden');
    $('#lb-equip').disabled = true; $('#lb-equip').textContent = 'Im Inventar';
  } else {
    dupe.classList.add('hidden'); $('#lb-equip').disabled = false; $('#lb-equip').textContent = 'Anlegen';
  }
  $('#lb-again').disabled = r.sparks < CURRENT_BOX.cost;
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

/* ---------- Boot ---------- */
async function boot() {
  try {
    const s = await api('state.php');
    if (s.logged_in) {
      renderProgress(s.progress);
      EQUIPPED = s.equipped || null; applyTanuki();
      showApp(); resetHero(); showView('today');
    } else showAuth();
  } catch (_) { showAuth(); }
}

initTheme(); initAuth(); initDump(); initQuick(); initHero(); initMore(); initNav(); initLootbox();
boot();

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}
