<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'Dashboard') - DeviceCare</title>
  <style>
    :root {
      --bg:       #0f1117;
      --surface:  #1a1d27;
      --surface2: #22263a;
      --border:   #2d3148;
      --text:     #e2e8f0;
      --muted:    #8892a4;
      --soft:     #a8b3c7;
      --cyan:     #22d3ee;
      --green:    #22c55e;
      --yellow:   #f59e0b;
      --red:      #ef4444;
      --blue:     #3b82f6;
      --ink:      #080b12;
      --radius:   10px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', system-ui, sans-serif;
      display: flex;
      flex-direction: column;
    }
    a { color: inherit; text-decoration: none; }

    .site-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background:
        linear-gradient(180deg, rgba(10,14,22,.94), rgba(13,17,25,.86)),
        rgba(15,17,23,.92);
      border-bottom: 1px solid rgba(45,49,72,.72);
      box-shadow: 0 18px 50px rgba(0,0,0,.28);
      backdrop-filter: blur(16px);
    }
    .site-header::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background:
        radial-gradient(circle at 16% 0%, rgba(34,211,238,.14), transparent 30%),
        radial-gradient(circle at 78% -15%, rgba(34,197,94,.1), transparent 27%),
        linear-gradient(90deg, transparent, rgba(255,255,255,.08), transparent);
      opacity: .82;
    }
    .nav-shell {
      position: relative;
      width: min(1200px, 100%);
      min-height: 72px;
      margin: 0 auto;
      padding: 10px 16px;
      display: grid;
      grid-template-columns: minmax(220px, auto) 1fr auto;
      align-items: center;
      gap: 18px;
    }
    .brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      min-width: max-content;
      outline: none;
    }
    .brand:focus-visible,
    .nav-links a:focus-visible,
    .nav-cta:focus-visible,
    .footer-links a:focus-visible {
      outline: 2px solid rgba(34,211,238,.9);
      outline-offset: 3px;
    }
    .brand-mark {
      position: relative;
      width: 42px;
      height: 42px;
      border: 1px solid rgba(34,211,238,.55);
      border-radius: 10px;
      display: grid;
      place-items: center;
      overflow: hidden;
      background:
        linear-gradient(135deg, rgba(59,130,246,.32), rgba(34,197,94,.16)),
        #101827;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.08), 0 14px 32px rgba(34,211,238,.12);
      color: #eaffff;
      font-size: .72rem;
      font-weight: 900;
      letter-spacing: .8px;
    }
    .brand-mark::before {
      content: "";
      position: absolute;
      width: 18px;
      height: 21px;
      border: 2px solid rgba(226,232,240,.9);
      border-radius: 8px 8px 10px 10px;
      clip-path: polygon(50% 0, 100% 18%, 100% 68%, 50% 100%, 0 68%, 0 18%);
      opacity: .48;
    }
    .brand-mark::after {
      content: "";
      position: absolute;
      left: 8px;
      right: 8px;
      top: 21px;
      height: 2px;
      background: linear-gradient(90deg, transparent 0 8%, var(--cyan) 8% 28%, transparent 28% 38%, var(--green) 38% 58%, transparent 58% 67%, var(--cyan) 67% 100%);
      box-shadow: 0 0 12px rgba(34,211,238,.72);
    }
    .brand-name { display: block; font-size: 1.03rem; font-weight: 850; line-height: 1.05; letter-spacing: .1px; }
    .brand-sub { display: block; color: var(--soft); font-size: .72rem; margin-top: 4px; }
    .nav-links {
      justify-self: center;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      min-width: 0;
      padding: 4px;
      border: 1px solid rgba(45,49,72,.9);
      border-radius: 10px;
      background: rgba(26,29,39,.62);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
    }
    .nav-links a {
      min-height: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 7px 12px;
      border: 1px solid transparent;
      border-radius: 7px;
      font-size: .84rem;
      font-weight: 750;
      color: var(--soft);
      white-space: nowrap;
      transition: background .15s, border-color .15s, color .15s, transform .15s;
    }
    .nav-links a:hover {
      background: rgba(34,211,238,.07);
      border-color: rgba(34,211,238,.22);
      color: var(--text);
      transform: translateY(-1px);
    }
    .nav-links a.active {
      background: linear-gradient(180deg, rgba(34,211,238,.2), rgba(59,130,246,.14));
      border-color: rgba(34,211,238,.42);
      color: #f8ffff;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
    }
    .nav-right {
      justify-self: end;
      display:flex;
      align-items:center;
      justify-content: flex-end;
      gap:10px;
      min-width: 0;
      font-size:.78rem;
      color:var(--soft);
    }
    .monitor-note {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 36px;
      padding: 7px 10px;
      border: 1px solid rgba(45,49,72,.82);
      border-radius: 8px;
      background: rgba(255,255,255,.03);
      white-space: nowrap;
    }
    .monitor-note::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 10px rgba(34,197,94,.8);
    }
    .nav-cta {
      min-height: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(34,211,238,.5);
      border-radius: 8px;
      padding: 7px 12px;
      background: linear-gradient(135deg, rgba(34,211,238,.18), rgba(34,197,94,.14));
      color: #f7ffff;
      font-size: .82rem;
      font-weight: 850;
      white-space: nowrap;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
      transition: background .15s, border-color .15s, transform .15s;
    }
    .nav-cta:hover {
      border-color: rgba(34,211,238,.8);
      background: linear-gradient(135deg, rgba(34,211,238,.26), rgba(34,197,94,.18));
      transform: translateY(-1px);
    }
    #nav-alert-badge {
      display:none;
      color:#fff;
      font-size:.65rem;
      font-weight:850;
      letter-spacing:.55px;
      padding:6px 10px;
      border:1px solid rgba(255,255,255,.18);
      border-radius:999px;
      cursor:pointer;
      box-shadow: 0 8px 24px rgba(0,0,0,.24);
      text-transform: uppercase;
      white-space: nowrap;
    }
    .container {
      width: min(1200px, 100%);
      margin: 0 auto;
      padding: 28px 16px;
    }
    .page-title { font-size: 1.3rem; font-weight: 750; margin-bottom: 4px; }
    .page-sub   { font-size: .85rem; color: var(--muted); margin-bottom: 24px; }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
    }
    .badge {
      display: inline-block;
      font-size: .68rem;
      font-weight: 750;
      padding: 3px 9px;
      border-radius: 999px;
      text-transform: uppercase;
    }
    .badge-normal   { background: rgba(34,197,94,.15);  color: var(--green); }
    .badge-warning  { background: rgba(245,158,11,.15); color: var(--yellow); }
    .badge-critical { background: rgba(239,68,68,.15);  color: var(--red); }
    .badge-offline  { background: rgba(136,146,164,.1); color: var(--muted); }
    .badge-unknown  { background: rgba(136,146,164,.1); color: var(--muted); }
    .bar-wrap { background: var(--surface2); border-radius: 4px; height: 6px; margin-top: 8px; }
    .bar-fill { height: 100%; border-radius: 4px; }
    .bar-green  { background: var(--green); }
    .bar-yellow { background: var(--yellow); }
    .bar-red    { background: var(--red); }
    .dot-online,
    .dot-offline {
      display:inline-block;
      width:8px;
      height:8px;
      border-radius:50%;
      margin-right:6px;
    }
    .dot-online  { background:var(--green); box-shadow:0 0 6px var(--green); }
    .dot-offline { background:var(--muted); }

    .ai-toggle {
      position: fixed;
      right: 20px;
      bottom: 20px;
      z-index: 160;
      width: 52px;
      height: 52px;
      border: 1px solid rgba(59,130,246,.55);
      border-radius: 999px;
      background: var(--blue);
      color: #fff;
      font-size: 1rem;
      font-weight: 850;
      box-shadow: 0 18px 40px rgba(0,0,0,.34);
      cursor: pointer;
    }
    .ai-panel {
      position: fixed;
      right: 20px;
      bottom: 84px;
      z-index: 160;
      width: min(390px, calc(100vw - 28px));
      max-height: min(640px, calc(100vh - 110px));
      display: none;
      grid-template-rows: auto auto 1fr auto;
      overflow: hidden;
      background: #151923;
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 24px 70px rgba(0,0,0,.42);
    }
    .ai-panel.open { display: grid; }
    .ai-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      padding: 14px 15px;
      border-bottom: 1px solid var(--border);
    }
    .ai-title { font-size: .95rem; font-weight: 850; }
    .ai-status { color: var(--muted); font-size: .74rem; margin-top: 3px; }
    .ai-close {
      width: 30px;
      height: 30px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--surface2);
      color: var(--text);
      cursor: pointer;
    }
    .ai-starters {
      display: flex;
      gap: 7px;
      overflow-x: auto;
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
    }
    .ai-starters button {
      flex: 0 0 auto;
      border: 1px solid var(--border);
      border-radius: 999px;
      background: rgba(255,255,255,.03);
      color: var(--text);
      padding: 7px 10px;
      font-size: .76rem;
      cursor: pointer;
      white-space: nowrap;
    }
    .ai-messages {
      min-height: 220px;
      max-height: 365px;
      overflow: auto;
      padding: 14px;
      display: grid;
      align-content: start;
      gap: 9px;
    }
    .ai-msg {
      width: fit-content;
      max-width: 88%;
      padding: 9px 11px;
      border-radius: 10px;
      font-size: .84rem;
      line-height: 1.45;
      white-space: pre-wrap;
    }
    .ai-msg.user { margin-left: auto; background: var(--blue); color: #fff; }
    .ai-msg.assistant { background: var(--surface2); color: var(--text); }
    .ai-msg.error { background: rgba(239,68,68,.13); color: #fecaca; border: 1px solid rgba(239,68,68,.28); }
    .ai-form {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px;
      padding: 12px;
      border-top: 1px solid var(--border);
    }
    .ai-form textarea {
      min-height: 42px;
      max-height: 100px;
      resize: vertical;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: #0f1117;
      color: var(--text);
      padding: 10px;
      font: inherit;
      font-size: .84rem;
    }
    .ai-send {
      min-width: 72px;
      border: 1px solid var(--blue);
      border-radius: 8px;
      background: var(--blue);
      color: #fff;
      font-weight: 800;
      cursor: pointer;
    }
    .ai-send:disabled,
    .ai-form textarea:disabled { opacity: .55; cursor: not-allowed; }

    .site-footer {
      margin-top: auto;
      border-top: 1px solid rgba(45,49,72,.72);
      background:
        linear-gradient(180deg, rgba(15,17,23,.42), rgba(8,11,18,.92)),
        rgba(15,17,23,.72);
    }
    .footer-shell {
      width: min(1200px, 100%);
      margin: 0 auto;
      padding: 28px 16px;
      display: grid;
      grid-template-columns: minmax(260px, 1.25fr) auto minmax(190px, auto);
      align-items: start;
      gap: 24px;
      color: var(--muted);
      font-size: .8rem;
    }
    .footer-brand-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 9px;
    }
    .footer-mark {
      width: 28px;
      height: 28px;
      border: 1px solid rgba(34,211,238,.4);
      border-radius: 8px;
      background: linear-gradient(135deg, rgba(34,211,238,.18), rgba(34,197,94,.12));
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.06);
    }
    .footer-brand { color: var(--text); font-weight: 850; line-height: 1; }
    .footer-copy { max-width: 430px; color: var(--soft); line-height: 1.55; }
    .footer-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: center;
    }
    .status-chip {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      min-height: 30px;
      padding: 6px 10px;
      border: 1px solid rgba(45,49,72,.86);
      border-radius: 8px;
      background: rgba(26,29,39,.58);
      color: var(--soft);
      font-size: .72rem;
      font-weight: 750;
      text-transform: uppercase;
      letter-spacing: .45px;
      white-space: nowrap;
    }
    .status-chip::before {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--cyan);
      box-shadow: 0 0 10px rgba(34,211,238,.75);
    }
    .status-chip.green::before { background: var(--green); box-shadow: 0 0 10px rgba(34,197,94,.75); }
    .footer-group {
      justify-self: end;
      display: grid;
      gap: 8px;
      min-width: 170px;
    }
    .footer-label {
      color: var(--soft);
      font-size: .68rem;
      font-weight: 850;
      letter-spacing: .72px;
      text-transform: uppercase;
    }
    .footer-links { display: flex; flex-wrap: wrap; gap: 10px 14px; }
    .footer-links a,
    .footer-links span {
      color: var(--muted);
      line-height: 1.5;
    }
    .footer-links a:hover { color: var(--text); }
    @media (max-width: 760px) {
      .nav-shell {
        min-height: auto;
        padding: 12px 14px;
        grid-template-columns: 1fr;
        gap: 12px;
      }
      .brand { min-width: 0; }
      .nav-links {
        justify-self: stretch;
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
      }
      .nav-links a { flex: 1 0 auto; }
      .nav-right {
        justify-self: stretch;
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
      }
      .monitor-note,
      .nav-cta { flex: 1 1 150px; }
      .container { padding: 20px 12px; }
      .footer-shell {
        grid-template-columns: 1fr;
        gap: 18px;
        padding: 24px 14px;
      }
      .footer-meta { justify-content: flex-start; }
      .footer-group { justify-self: stretch; }
      .ai-toggle { right: 14px; bottom: 14px; }
      .ai-panel { right: 14px; bottom: 76px; }
    }

    @yield('styles')
  </style>
  @yield('head')
</head>
<body>

@php
  $navDevice = request()->route('device');
@endphp
<header class="site-header">
  <div class="nav-shell">
    <a href="{{ route('dashboard.index') }}" class="brand" aria-label="DeviceCare beranda">
      <span class="brand-mark">DC</span>
      <span>
        <span class="brand-name">DeviceCare</span>
        <span class="brand-sub">Laptop Health Command</span>
      </span>
    </a>

    <nav class="nav-links" aria-label="Navigasi utama">
      <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.index') ? 'active' : '' }}">Beranda</a>
      @if($navDevice)
        <a href="{{ route('dashboard.show', $navDevice) }}" class="{{ request()->routeIs('dashboard.show') ? 'active' : '' }}">Monitor</a>
        <a href="{{ route('dashboard.diagnosis', $navDevice) }}" class="{{ request()->routeIs('dashboard.diagnosis') ? 'active' : '' }}">Diagnosis</a>
        <a href="{{ route('dashboard.report', $navDevice) }}" class="{{ request()->routeIs('dashboard.report') ? 'active' : '' }}">Report</a>
      @endif
    </nav>

    <div class="nav-right">
      <span id="nav-alert-badge">CRITICAL</span>
      @if($navDevice)
        <a class="nav-cta" href="{{ route('dashboard.show', $navDevice) }}">Buka Monitor</a>
      @else
        <span class="monitor-note">Personal monitoring</span>
      @endif
    </div>
  </div>
</header>

<main class="container">
  @yield('content')
</main>

<footer class="site-footer">
  <div class="footer-shell">
    <div>
      <div class="footer-brand-row">
        <span class="footer-mark" aria-hidden="true"></span>
        <div class="footer-brand">DeviceCare</div>
      </div>
      <div class="footer-copy">Monitoring kesehatan laptop personal untuk suhu, baterai, storage, RAM, dan sinyal diagnosis harian.</div>
    </div>
    <div class="footer-meta" aria-label="Metadata produk">
      <span class="status-chip green">Realtime</span>
      <span class="status-chip">AI Ready</span>
      <span class="status-chip">Dashboard</span>
    </div>
    <div class="footer-group">
      <div class="footer-label">Navigasi</div>
      <div class="footer-links">
        <a href="{{ route('dashboard.index') }}">Beranda</a>
        @if($navDevice)
          <a href="{{ route('dashboard.show', $navDevice) }}">Monitor</a>
          <a href="{{ route('dashboard.report', $navDevice) }}">Report</a>
        @endif
        <span>{{ date('Y') }}</span>
      </div>
    </div>
  </div>
</footer>

<button class="ai-toggle" id="ai-toggle" type="button" aria-label="Buka DeviceCare AI">AI</button>
<section class="ai-panel" id="ai-panel" aria-label="DeviceCare AI Assistant">
  <div class="ai-head">
    <div>
      <div class="ai-title">DeviceCare AI</div>
      <div class="ai-status" id="ai-status">Membaca konteks laptop...</div>
    </div>
    <button class="ai-close" id="ai-close" type="button" aria-label="Tutup">x</button>
  </div>
  <div class="ai-starters" id="ai-starters"></div>
  <div class="ai-messages" id="ai-messages">
    <div class="ai-msg assistant">Buka monitor laptop, lalu tanya kondisi suhu, baterai, storage, RAM, atau penyebab laptop terasa lambat.</div>
  </div>
  <form class="ai-form" id="ai-form">
    <textarea id="ai-input" rows="1" placeholder="Tanya kondisi laptop ini..."></textarea>
    <button class="ai-send" id="ai-send" type="submit">Kirim</button>
  </form>
</section>

<script>
(function(){
  const match = location.pathname.match(/\/devices\/(\d+)/);
  const devId = match ? match[1] : null;

  async function pollBadge(){
    if(!devId) return;
    try{
      const response = await fetch(`/api/devices/${devId}/alerts/unread-count`);
      const data = await response.json();
      const badge = document.getElementById('nav-alert-badge');
      if(!badge) return;

      if(data.has_critical){
        badge.style.display = 'inline-block';
        badge.style.background = '#ef4444';
        badge.style.color = '#fff';
        badge.textContent = 'CRITICAL';
      } else if(data.count > 0){
        badge.style.display = 'inline-block';
        badge.style.background = '#f59e0b';
        badge.style.color = '#111827';
        badge.textContent = `${data.count} alert`;
      } else {
        badge.style.display = 'none';
      }
    }catch(e){}
  }

  document.getElementById('nav-alert-badge')?.addEventListener('click', () => {
    const current = location.pathname.match(/\/devices\/(\d+)/);
    if(current) location.href = `/devices/${current[1]}/alerts`;
  });

  pollBadge();
  if(devId) setInterval(pollBadge, 10000);

  const panel = document.getElementById('ai-panel');
  const toggle = document.getElementById('ai-toggle');
  const close = document.getElementById('ai-close');
  const status = document.getElementById('ai-status');
  const starters = document.getElementById('ai-starters');
  const messages = document.getElementById('ai-messages');
  const form = document.getElementById('ai-form');
  const input = document.getElementById('ai-input');
  const send = document.getElementById('ai-send');
  const history = [];

  function addMessage(role, text, error = false, track = true){
    const bubble = document.createElement('div');
    bubble.className = `ai-msg ${error ? 'error' : role}`;
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    if(track && !error && (role === 'user' || role === 'assistant')){
      history.push({ role, content: text });
      if(history.length > 10) history.shift();
    }
    return bubble;
  }

  function setAiReady(ready, text){
    status.textContent = text;
    input.disabled = !ready;
    send.disabled = !ready;
    input.placeholder = ready ? 'Tanya kondisi laptop ini...' : 'Buka halaman monitor laptop dulu';
  }

  async function loadAi(){
    if(!devId){
      setAiReady(false, 'Buka salah satu laptop untuk memakai AI.');
      starters.innerHTML = '';
      return;
    }

    try{
      const context = await fetch(`/api/devices/${devId}/chat/context`).then(r => r.json());
      setAiReady(Boolean(context.ai_ready), context.ai_ready ? `Siap membaca data laptop ini (${context.model}).` : 'AI belum siap atau belum ada data laptop.');
    }catch(e){
      setAiReady(false, 'Konteks AI belum bisa dimuat.');
    }

    try{
      const data = await fetch(`/api/devices/${devId}/chat/starters`).then(r => r.json());
      starters.innerHTML = '';
      (data.starters || []).slice(0, 5).forEach(item => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = item.text || item;
        button.addEventListener('click', () => {
          input.value = button.textContent;
          form.requestSubmit();
        });
        starters.appendChild(button);
      });
    }catch(e){}
  }

  toggle?.addEventListener('click', () => {
    panel.classList.toggle('open');
    if(panel.classList.contains('open')) input.focus();
  });
  close?.addEventListener('click', () => panel.classList.remove('open'));

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = input.value.trim();
    if(!message || !devId) return;
    input.value = '';
    const priorHistory = history.slice();
    addMessage('user', message);
    const pending = addMessage('assistant', 'Sedang menganalisis data laptop...', false, false);
    send.disabled = true;

    try{
      const response = await fetch(`/api/devices/${devId}/chat/simple`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ message, history: priorHistory })
      });
      const data = await response.json();
      if(!response.ok || !data.success) throw new Error(data.error || 'AI belum bisa menjawab saat ini.');
      pending.textContent = data.response;
      history.push({ role: 'assistant', content: data.response });
    }catch(e){
      pending.remove();
      addMessage('assistant', e.message || 'AI belum bisa menjawab saat ini.', true);
    }finally{
      send.disabled = false;
    }
  });

  loadAi();
})();
</script>

@yield('scripts')
</body>
</html>



