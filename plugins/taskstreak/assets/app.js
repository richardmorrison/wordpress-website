/**
 * TaskStreak Frontend
 * Vanilla JS, calm coach vibe, and careful comments so you can learn the why.
 *
 * Key idea:
 * - All rendering is deterministic from state
 * - Network calls are isolated in api()
 * - Calm coach messages use a deck so repeats are rare
 */

const state = {
  tab: 'tasks',
  tasks: [],
  order: [],
  locked: 1,
  settings: null,
  history: [],
  today: null,
  message: null,
  snack: null,
};

function el(html) {
  const t = document.createElement('template');
  t.innerHTML = html.trim();
  return t.content.firstElementChild;
}

function escapeHtml(s) {
  return String(s)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#39;');
}

async function api(path, opts={}) {
  const res = await fetch(TASKSTREAK.rest + path, {
    ...opts,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': TASKSTREAK.nonce,
      ...(opts.headers || {})
    }
  });
  if (!res.ok) throw new Error('api ' + res.status);
  return await res.json();
}

async function safeApi(path, opts={}) {
  try { return await api(path, opts); }
  catch { return null; }
}

/* ---------------- Calm coach phrase deck ---------------- */

let PHRASES = null;

async function loadPhrases() {
  if (PHRASES) return PHRASES;
  try {
    const res = await fetch(TASKSTREAK.assets + 'phrases.json');
    PHRASES = await res.json();
  } catch {
    PHRASES = { micro_win:['Nice work.'], gentle_nudge:['A gentle nudge.'], playful_mix:['Steady progress.'], reflection_prompt:['What helped today?'] };
  }
  return PHRASES;
}

function deckKey(section){ return 'ts_deck_' + section; }
function deckPosKey(section){ return 'ts_deck_pos_' + section; }

function shuffle(arr) {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    const tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
  }
  return arr;
}

function ensureDeck(section, n) {
  let deck = [];
  let pos = 0;
  try { deck = JSON.parse(localStorage.getItem(deckKey(section)) || '[]'); } catch {}
  pos = Number(localStorage.getItem(deckPosKey(section)) || 0);

  if (!deck.length || deck.length !== n || pos >= deck.length) {
    deck = shuffle(Array.from({length:n}, (_,i)=>i));
    pos = 0;
    localStorage.setItem(deckKey(section), JSON.stringify(deck));
    localStorage.setItem(deckPosKey(section), String(pos));
  }

  return { deck, pos };
}

async function nextPhrase(section) {
  await loadPhrases();
  const list = (PHRASES && PHRASES[section]) ? PHRASES[section] : [];
  if (!list.length) return '';
  const { deck, pos } = ensureDeck(section, list.length);
  const idx = deck[pos];
  localStorage.setItem(deckPosKey(section), String(pos + 1));
  return list[idx] || '';
}

/* ---------------- App logic helpers ---------------- */

function applyTheme(theme) {
  document.body.classList.remove('ts-dark');
  if (theme === 'dark') document.body.classList.add('ts-dark');
  if (theme === 'system') {
    const dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (dark) document.body.classList.add('ts-dark');
  }
}

function tierIcon(t) {
  if (t === 'trophy') return ['purple', '#ts-trophy'];
  if (t === 'flame') return ['orange', '#ts-flame'];
  return ['green', '#ts-check'];
}

function percent(done, due) {
  if (!due) return 0;
  return Math.round((done / due) * 100);
}

function currentBadge(history) {
  const now = Date.now();
  return (history || []).find(h => (h.kind || '').startsWith('milestone_') && h.expires_at && (new Date(h.expires_at).getTime() > now)) || null;
}

function badgeLabel(kind) {
  const m = String(kind||'').match(/milestone_(\d+)/);
  const days = m ? Number(m[1]) : 0;
  if (days === 3) return '3 day sparkle';
  if (days === 7) return '7 day steady';
  if (days === 14) return '2 week trophy';
  if (days === 30) return '30 day legend';
  return 'Badge';
}

function daysMaskToLabel(mask) {
  const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const picked = [];
  for (let i=0;i<7;i++) if (mask & (1<<i)) picked.push(names[i]);
  return picked.length === 7 ? 'Every day' : picked.join(', ');
}

function dueToday(task) {
  // Server already provides today and task mask, but frontend can also compute using local day.
  // We keep it simple and treat all tasks as due for progress math.
  return true;
}

/* ---------------- Rendering ---------------- */

function render() {
  const root = document.getElementById('taskstreak-root');
  if (!root) return;

  const tasks = orderedTasks();
  const done = tasks.filter(t => t.done_today).length;
  const due = tasks.length;
  const pct = percent(done, due);

  root.innerHTML = `
    <div class="ts-wrap">
      ${renderHeader()}
      ${renderTabs()}
      ${state.tab === 'tasks' ? renderTasks(tasks, pct, done, due) : ''}
      ${state.tab === 'progress' ? renderProgressTab(tasks) : ''}
      ${state.tab === 'settings' ? renderSettingsTab() : ''}
    </div>
    ${state.snack ? renderSnack() : ''}
  `;

  bindUI();
  requestAnimationFrame(()=>{
    const fill = document.querySelector('.ts-progress-fill');
    if (fill) fill.style.width = pct + '%';
  });
}

function renderHeader() {
  return `
    <div class="ts-top">
      <div class="ts-logo">
        <svg width="20" height="20" aria-hidden="true"><use href="#ts-flame"></use></svg>
      </div>
      <div style="min-width:0">
        <h1 class="ts-h1">TaskStreak</h1>
        <div class="ts-sub">Quiet progress, one day at a time.</div>
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Order</h2>
        <span class="ts-pill">Optional</span>
      </div>
      <div class="ts-lock">
        <button class="ts-btn ghost" id="ts-lock-toggle">${state.locked ? 'Unlock order' : 'Lock order'}</button>
        <span class="ts-small">Drag to reorder, then lock.</span>
      </div>
    </div>
  `;
}

function renderTabs() {
  return `
    <div class="ts-tabs">
      <button class="ts-tab ${state.tab==='tasks'?'active':''}" data-tab="tasks">My Tasks</button>
      <button class="ts-tab ${state.tab==='progress'?'active':''}" data-tab="progress">Progress</button>
      <button class="ts-tab ${state.tab==='settings'?'active':''}" data-tab="settings"><svg width="18" height="18" style="vertical-align:-3px"><use href="#ts-gear"></use></svg></button>
    </div>
  `;
}

function renderMessageCard() {
  const calm = (state.settings && state.settings.calm_mode) ? state.settings.calm_mode : 'subtle';
  if (calm !== 'always' && !state.message) return '';
  const msg = state.message || '';
  if (!msg) return '';
  return `
    <div class="ts-card ts-section">
      <div class="ts-small" style="font-size:13px">${escapeHtml(msg)}</div>
    </div>
  `;
}

function renderBadgeCard() {
  const b = currentBadge(state.history);
  if (!b) return '';
  return `
    <div class="ts-card ts-section">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="ts-ic purple"><svg width="20" height="20"><use href="#ts-trophy"></use></svg></div>
        <div class="ts-task-main">
          <div class="ts-task-title">Current badge</div>
          <div class="ts-task-meta">${escapeHtml(badgeLabel(b.kind))} · fades from main screen, stays in history.</div>
        </div>
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Order</h2>
        <span class="ts-pill">Optional</span>
      </div>
      <div class="ts-lock">
        <button class="ts-btn ghost" id="ts-lock-toggle">${state.locked ? 'Unlock order' : 'Lock order'}</button>
        <span class="ts-small">Drag to reorder, then lock.</span>
      </div>
    </div>
  `;
}

function renderTasks(tasks, pct, done, due) {
  return `
    ${renderMessageCard()}
    ${renderBadgeCard()}
    <div class="ts-card ts-progress-card">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:1100">Today</div>
        <div class="ts-pill">${pct}%</div>
      </div>
      <div class="ts-progress-row">
        <div class="ts-progress-bar" style="flex:1">
          <div class="ts-progress-fill"></div>
        </div>
        <div class="ts-pill">${done}/${due}</div>
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Tasks</h2>
        
      </div>

      <div id="ts-task-list">
        ${tasks.length ? tasks.map(t => renderTaskRow(t)).join('') : `<div class="ts-small">No tasks yet. Add one below.</div>`}
      </div>
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Add a task</h2>
        <span class="ts-pill">Small steps count</span>
      </div>

      <div class="ts-small">Repeats on selected days. Leave repeats blank for ongoing.</div>

      <form class="ts-form" id="ts-add-form">
        <input class="ts-input" name="title" placeholder="e.g., Stretch, read, water" autocomplete="off">
        <button class="ts-btn primary" type="submit">Add</button>
      </form>

      <div style="margin-top:12px" class="ts-small">
        Repeats:
        <input class="ts-input" style="width:120px;display:inline-block;margin-left:8px;padding:10px" name="repeats" id="ts-repeats" placeholder="Unlimited">
      </div>

      <div style="margin-top:12px" class="ts-small">
        Reps needed:
        <input class="ts-input" style="width:90px;display:inline-block;margin-left:8px;padding:10px" name="reps_target" id="ts-reps-target" placeholder="1">
      </div>

      <div style="margin-top:12px" class="ts-small">Days:</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px" id="ts-days">
        ${['S','M','T','W','T','F','S'].map((d,i)=>`<button type="button" class="ts-btn ghost" data-day="${i}">${d}</button>`).join('')}
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Order</h2>
        <span class="ts-pill">Optional</span>
      </div>
      <div class="ts-lock">
        <button class="ts-btn ghost" id="ts-lock-toggle">${state.locked ? 'Unlock order' : 'Lock order'}</button>
        <span class="ts-small">Drag to reorder, then lock.</span>
      </div>
    </div>
  `;
}

function renderTaskRow(t) {
  const [color, icon] = tierIcon(t.tier);
  const doneClass = t.done_today ? 'done' : '';
  const grace = t.grace_active ? `<span class="ts-pill" title="Grace active">🕊️</span>` : '';
  return `
    <div class="ts-task ${doneClass}" draggable="${state.locked ? 'false' : 'true'}" data-id="${t.id}">
      <div class="ts-ic ${color}"><svg width="20" height="20"><use href="${icon}"></use></svg></div>
      <div class="ts-task-main">
        <div class="ts-task-title">${escapeHtml(t.title)}</div>
        <div class="ts-task-meta">Streak: ${t.streak_days} days · ${t.reps_today || 0}/${t.reps_target || 1} reps today · ${escapeHtml(daysMaskToLabel(t.days_mask))}</div>
      </div>
      ${grace}
      <button class="ts-btn ${t.done_today ? 'ghost' : 'primary'}" data-action="toggle" data-id="${t.id}">
        ${t.done_today ? 'Undo' : '+'}
      </button>
    </div>
  `;
}

function renderProgressTab(tasks) {
  const done = tasks.filter(t => t.done_today).length;
  const due = tasks.length;
  const pct = percent(done, due);
  const full = (due > 0 && done === due) ? 1 : 0;
  const minor = (due > 0 && (done / due) >= 0.7) ? 1 : 0;

  return `
    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Progress</h2>
        <span class="ts-pill">${pct}% today</span>
      </div>

      <div class="ts-grid">
        <div class="ts-metric">
          <div class="n">${tasks.length}</div>
          <div class="l">Active tasks</div>
        </div>
        <div class="ts-metric">
          <div class="n">${done}</div>
          <div class="l">Done today</div>
        </div>
        <div class="ts-metric">
          <div class="n">${full}</div>
          <div class="l">Full streak today</div>
        </div>
        <div class="ts-metric">
          <div class="n">${minor}</div>
          <div class="l">Minor streak today</div>
        </div>
      </div>

      <div style="margin-top:14px" class="ts-small">
        Streak tiers: ✅ new · 🔥 active · 🏆 at 2 weeks. If you miss, the tier gently steps back.
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Order</h2>
        <span class="ts-pill">Optional</span>
      </div>
      <div class="ts-lock">
        <button class="ts-btn ghost" id="ts-lock-toggle">${state.locked ? 'Unlock order' : 'Lock order'}</button>
        <span class="ts-small">Drag to reorder, then lock.</span>
      </div>
    </div>
  `;
}

function renderHistorySection() {
  const items = state.history || [];
  const badges = items.filter(h => (h.kind || '').startsWith('milestone_'));
  const reflections = items.filter(h => (h.kind || '') === 'reflection');

  return `
    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>History</h2>
        <span class="ts-pill">Quiet proof</span>
      </div>

      <div class="ts-small" style="margin-bottom:10px">Badges fade from the main screen, but they live here.</div>

      ${badges.length ? badges.slice(0,20).map(b => `
        <div class="ts-history-item">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="ts-ic purple"><svg width="18" height="18"><use href="#ts-trophy"></use></svg></div>
            <div style="flex:1">
              <div style="font-weight:1000">${escapeHtml(badgeLabel(b.kind))}</div>
              <div class="ts-small">Earned ${escapeHtml(b.earned_at || '')}</div>
            </div>
          </div>
        </div>
      `).join('') : `<div class="ts-small">No badges yet. They show up naturally.</div>`}

      <div style="height:14px"></div>

      <div class="ts-section-title" style="margin-bottom:10px">
        <h2>Reflections</h2>
        <span class="ts-pill">Optional</span>
      </div>

      <form id="ts-reflect-form">
        <textarea class="ts-input" name="note" rows="3" style="width:100%;resize:vertical" placeholder="One line is enough. What helped today?"></textarea>
        <div style="display:flex;gap:10px;margin-top:10px;align-items:center;justify-content:space-between">
          <div class="ts-small" id="ts-reflect-prompt"></div>
          <button class="ts-btn primary" type="submit">Save</button>
        </div>
      </form>

      <div style="margin-top:12px">
        ${reflections.length ? reflections.slice(0,10).map(r => `
          <div class="ts-history-item">
            <div style="font-weight:1000">Reflection</div>
            <div class="ts-small">${escapeHtml(r.note || '')}</div>
          </div>
        `).join('') : `<div class="ts-small">No reflections yet.</div>`}
      </div>
    </div>

    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Order</h2>
        <span class="ts-pill">Optional</span>
      </div>
      <div class="ts-lock">
        <button class="ts-btn ghost" id="ts-lock-toggle">${state.locked ? 'Unlock order' : 'Lock order'}</button>
        <span class="ts-small">Drag to reorder, then lock.</span>
      </div>
    </div>
  `;
}

function renderSettingsTab() {
  const s = state.settings || {};
  return `
    <div class="ts-card ts-section">
      <div class="ts-section-title">
        <h2>Settings</h2>
        <a class="ts-link" href="${escapeHtml(TASKSTREAK.pageUrl)}"><svg width="16" height="16" style="vertical-align:-3px"><use href="#ts-link"></use></svg> App page</a>
      </div>

      <div class="ts-small">Theme</div>
      <div style="display:flex;gap:10px;margin-top:8px">
        ${['light','dark','system'].map(v=>`<button class="ts-btn ${s.theme===v?'primary':'ghost'}" data-setting="theme" data-value="${v}">${v}</button>`).join('')}
      </div>

      <div style="height:12px"></div>

      <div class="ts-small">Week starts on</div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap">
        ${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map((d,i)=>`<button class="ts-btn ${Number(s.week_start)===i?'primary':'ghost'}" data-setting="week_start" data-value="${i}">${d}</button>`).join('')}
      </div>

      <div style="height:12px"></div>

      <div class="ts-small">Progress style</div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap">
        ${['bar','ring','dots','wave'].map(v=>`<button class="ts-btn ${s.progress_style===v?'primary':'ghost'}" data-setting="progress_style" data-value="${v}">${v}</button>`).join('')}
      </div>

      <div style="height:12px"></div>

      <div class="ts-small">Progress mode</div>
      <div style="display:flex;gap:10px;margin-top:8px">
        ${['rotate','fixed'].map(v=>`<button class="ts-btn ${s.progress_mode===v?'primary':'ghost'}" data-setting="progress_mode" data-value="${v}">${v}</button>`).join('')}
      </div>

      <div style="height:12px"></div>

      <div class="ts-small">Sync preference</div>
      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap">
        ${['ask','device','latest'].map(v=>`<button class="ts-btn ${s.sync_preference===v?'primary':'ghost'}" data-setting="sync_preference" data-value="${v}">${v}</button>`).join('')}
      </div>

      <div style="height:12px"></div>

      <div class="ts-small">Calm coach messages</div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="ts-btn ${s.calm_mode==='subtle'?'primary':'ghost'}" data-setting="calm_mode" data-value="subtle">Subtle</button>
        <button class="ts-btn ${s.calm_mode==='always'?'primary':'ghost'}" data-setting="calm_mode" data-value="always">Always visible</button>
      </div>

      <div class="ts-small" style="margin-top:10px">
        Subtle means messages appear on app load and on actions, then get out of your way.
      </div>
    </div>

    ${renderHistorySection()}
  `;
}

function renderSnack() {
  return `
    <div class="ts-snack">
      <p>${escapeHtml(state.snack.text)}</p>
      <button id="ts-snack-undo">Undo</button>
    </div>
  `;
}

/* ---------------- Ordering and drag ---------------- */

function orderedTasks() {
  const tasks = [...state.tasks];

  // Completed tasks always drift to the end.
  tasks.sort((a,b)=>{
    if (a.done_today === b.done_today) return 0;
    return a.done_today ? 1 : -1;
  });

  if (!state.order || !state.order.length) return tasks;

  const pos = new Map();
  state.order.forEach((id, i)=>pos.set(Number(id), i));

  tasks.sort((a,b)=>{
    const ad = a.done_today ? 1 : 0;
    const bd = b.done_today ? 1 : 0;
    if (ad !== bd) return ad - bd;

    const ai = pos.has(a.id) ? pos.get(a.id) : 9999;
    const bi = pos.has(b.id) ? pos.get(b.id) : 9999;
    return ai - bi;
  });

  return tasks;
}

function saveOrderFromDOM() {
  const list = document.getElementById('ts-task-list');
  if (!list) return;
  const ids = [...list.querySelectorAll('.ts-task')].map(n => Number(n.dataset.id));
  state.order = ids;
  safeApi('/order', { method:'POST', body: JSON.stringify({ order: ids, locked: state.locked }) });
}

/* ---------------- UI binding ---------------- */

function bindUI() {
  document.querySelectorAll('.ts-tab').forEach(b=>{
    b.addEventListener('click', ()=>{
      state.tab = b.dataset.tab;
      if (state.settings && state.settings.calm_mode === 'subtle') state.message = null;
      render();
    });
  });

  document.querySelectorAll('[data-action="toggle"]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = Number(btn.dataset.id);
      const task = state.tasks.find(t=>t.id===id);
      if (!task) return;

      if (task.done_today) {
        await safeApi('/complete', { method:'POST', body: JSON.stringify({ task_id:id, undo: true }) });
      } else {
        const res = await safeApi('/complete', { method:'POST', body: JSON.stringify({ task_id:id }) });
        if (res && res.just_completed) {
          celebrate();
        }
        const msg = await nextPhrase('micro_win');
        showSnack(msg || 'Nice. Undo?', id);
      }

      await refreshData();
    });
  });

  const addForm = document.getElementById('ts-add-form');
  if (addForm) {
    addForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(addForm);
      const title = String(fd.get('title') || '').trim();
      if (!title) return;

      const repeats = String(document.getElementById('ts-repeats').value || '').trim();
      const days = selectedDays();

      await safeApi('/tasks', { method:'POST', body: JSON.stringify({
        title,
        repeats_limit: repeats ? Number(repeats) : null,
        days,
        reps_target: Number(document.getElementById('ts-reps-target')?.value || 1)
      })});

      addForm.reset();
      const rt = document.getElementById('ts-reps-target');
      if (rt) rt.value = '';
      clearDayButtons();
      const msg = await nextPhrase('playful_mix');
      if (state.settings && state.settings.calm_mode === 'always') state.message = msg;
      await refreshData();
    });
  }

  const lockBtn = document.getElementById('ts-lock-toggle');
  if (lockBtn) {
    lockBtn.addEventListener('click', async ()=>{
      state.locked = state.locked ? 0 : 1;
      await safeApi('/order', { method:'POST', body: JSON.stringify({ order: state.order, locked: state.locked }) });
      render();
    });
  }

  // Settings buttons
  document.querySelectorAll('[data-setting]').forEach(b=>{
    b.addEventListener('click', async ()=>{
      const key = b.dataset.setting;
      const val = b.dataset.value;

      const payload = {};
      payload[key] = (key === 'week_start') ? Number(val) : val;

      const next = await safeApi('/settings', { method:'POST', body: JSON.stringify(payload) });
      if (next) state.settings = next;

      applyTheme(state.settings.theme);

      // Calm mode subtle: keep message mostly event-driven
      if (key === 'calm_mode' && val === 'subtle') state.message = null;

      render();
    });
  });

  // Reflection
  const reflectForm = document.getElementById('ts-reflect-form');
  if (reflectForm) {
    nextPhrase('reflection_prompt').then(p=>{
      const el = document.getElementById('ts-reflect-prompt');
      if (el) el.textContent = p;
    });

    reflectForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(reflectForm);
      const note = String(fd.get('note') || '').trim();
      if (!note) return;

      await safeApi('/history', { method:'POST', body: JSON.stringify({ note }) });
      reflectForm.reset();
      await refreshData();
    });
  }

  // Drag and drop ordering (only when unlocked)
  const list = document.getElementById('ts-task-list');
  if (list && !state.locked) {
    let dragEl = null;

    list.querySelectorAll('.ts-task').forEach(item=>{
      item.addEventListener('dragstart', (e)=>{
        dragEl = item;
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });

      item.addEventListener('dragend', ()=>{
        if (dragEl) dragEl.classList.remove('dragging');
        dragEl = null;
        saveOrderFromDOM();
      });

      item.addEventListener('dragover', (e)=>{
        e.preventDefault();
        const target = item;
        if (!dragEl || dragEl === target) return;
        const rect = target.getBoundingClientRect();
        const after = (e.clientY - rect.top) > (rect.height / 2);
        if (after) target.after(dragEl);
        else target.before(dragEl);
      });
    });
  }
}

function celebrate() {
  // Lightweight celebration: tiny emoji burst that cleans itself up.
  const root = document.getElementById('taskstreak-root');
  if (!root) return;
  const wrap = document.createElement('div');
  wrap.className = 'ts-celebrate';
  const emojis = ['✨','🎉','💙','⭐'];
  for (let i = 0; i < 14; i++) {
    const s = document.createElement('span');
    s.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    s.style.left = (50 + (Math.random() * 18 - 9)) + '%';
    s.style.top = (18 + (Math.random() * 10 - 5)) + 'px';
    s.style.setProperty('--dx', (Math.random() * 140 - 70) + 'px');
    s.style.setProperty('--dy', (Math.random() * 160 + 80) + 'px');
    s.style.setProperty('--dr', (Math.random() * 120 - 60) + 'deg');
    wrap.appendChild(s);
  }
  root.appendChild(wrap);
  setTimeout(()=>wrap.remove(), 900);
}

function showSnack(text, taskId) {
  state.snack = { text, taskId };
  render();
  const undoBtn = document.getElementById('ts-snack-undo');
  if (undoBtn) {
    undoBtn.onclick = async ()=>{
      await safeApi('/complete', { method:'POST', body: JSON.stringify({ task_id: taskId, undo: true }) });
      state.snack = null;
      await refreshData();
    };
  }

  // Auto dismiss after a few seconds
  setTimeout(()=>{
    if (state.snack && state.snack.taskId === taskId) {
      state.snack = null;
      render();
    }
  }, 4500);
}

/* ---------------- Day selection for new tasks ---------------- */

const daySelection = new Set();

function selectedDays() {
  if (!daySelection.size) return [0,1,2,3,4,5,6];
  return [...daySelection.values()];
}

function clearDayButtons() {
  daySelection.clear();
  document.querySelectorAll('#ts-days [data-day]').forEach(b=>{
    b.classList.remove('primary');
    b.classList.add('ghost');
  });
}

function bindDayButtons() {
  const wrap = document.getElementById('ts-days');
  if (!wrap) return;
  wrap.querySelectorAll('[data-day]').forEach(b=>{
    b.addEventListener('click', ()=>{
      const d = Number(b.dataset.day);
      if (daySelection.has(d)) daySelection.delete(d);
      else daySelection.add(d);

      b.classList.toggle('primary');
      b.classList.toggle('ghost');
    });
  });
}

/* ---------------- Boot ---------------- */

async function refreshData() {
  state.tasks = await api('/tasks');
  const order = await api('/order');
  state.order = order.order || [];
  state.locked = order.locked ? 1 : 0;
  state.history = await api('/history');
  render();
  bindDayButtons();
}

async function boot() {
  // Load icon sprite
  try{
    const svg = await fetch(TASKSTREAK.assets + 'icons/icons.svg').then(r=>r.text());
    document.body.insertAdjacentHTML('afterbegin', svg);
  } catch {}

  const boot = await api('/boot');
  state.today = boot.today;
  state.settings = boot.settings;
  applyTheme(state.settings.theme);

  await loadPhrases();

  // Calm mode option b (subtle): show message on load, then get out of the way
  if (state.settings.calm_mode === 'subtle') {
    state.message = await nextPhrase('playful_mix');
  } else {
    state.message = await nextPhrase('playful_mix');
  }

  await refreshData();
}

document.addEventListener('DOMContentLoaded', ()=>{
  const root = document.getElementById('taskstreak-root');
  if (!root) return;
  boot();
});
