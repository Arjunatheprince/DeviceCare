@extends('layouts.app')

@section('title', 'Alerts — '.$device->device_name)

@section('styles')
<style>
  .back-link { font-size:.82rem;color:var(--muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;transition:color .15s; }
  .back-link:hover { color:var(--text); }

  .sub-nav { display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:0; }
  .sub-nav a {
    padding:8px 16px;font-size:.85rem;color:var(--muted);border-radius:8px 8px 0 0;
    border:1px solid transparent;border-bottom:none;transition:color .15s,background .15s;margin-bottom:-1px;
  }
  .sub-nav a:hover { color:var(--text);background:var(--surface2); }
  .sub-nav a.active { color:var(--text);background:var(--surface);border-color:var(--border); }

  /* stat bar */
  .stat-bar { display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap; }
  .stat-card {
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    padding:14px 20px;flex:1;min-width:140px;display:flex;align-items:center;gap:14px;
  }
  .stat-card.critical { border-color:var(--red);    background:rgba(239,68,68,.06); }
  .stat-card.warning  { border-color:var(--yellow); background:rgba(245,158,11,.06); }
  .stat-card.ok       { border-color:var(--green);  background:rgba(34,197,94,.06); }
  .stat-icon { font-size:1.6rem; }
  .stat-body .stat-val  { font-size:1.5rem;font-weight:700; }
  .stat-body .stat-label{ font-size:.75rem;color:var(--muted); }

  /* toolbar */
  .toolbar { display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px; }
  .section-title { font-size:.85rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.7px; }
  .btn-resolve-all {
    padding:6px 16px;border-radius:8px;font-size:.8rem;cursor:pointer;
    background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:var(--blue);
    transition:all .15s;
  }
  .btn-resolve-all:hover { background:rgba(59,130,246,.2); }

  /* alert cards */
  .alert-list { display:flex;flex-direction:column;gap:10px;margin-bottom:28px; }
  .alert-card {
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    padding:16px 20px;display:flex;gap:16px;align-items:flex-start;
  }
  .alert-card.critical { border-left:4px solid var(--red); }
  .alert-card.warning  { border-left:4px solid var(--yellow); }
  .alert-card.resolved { opacity:.55;border-left:4px solid var(--muted); }

  .alert-icon { font-size:1.4rem;flex-shrink:0;margin-top:2px; }
  .alert-body { flex:1;min-width:0; }
  .alert-title { font-size:.9rem;font-weight:600;margin-bottom:4px; }
  .alert-msg   { font-size:.82rem;color:var(--muted);margin-bottom:8px;line-height:1.5; }
  .alert-suggs { list-style:none;display:flex;flex-direction:column;gap:4px;margin-bottom:8px; }
  .alert-suggs li { font-size:.79rem;color:var(--text); }
  .alert-suggs li::before { content:"→ ";color:var(--blue); }
  .alert-meta { display:flex;gap:12px;align-items:center;flex-wrap:wrap; }
  .alert-time { font-size:.73rem;color:var(--muted); }

  .alert-actions { flex-shrink:0;display:flex;flex-direction:column;gap:6px;align-items:flex-end; }
  .btn-resolve {
    padding:4px 12px;border-radius:6px;font-size:.75rem;cursor:pointer;
    background:var(--surface2);border:1px solid var(--border);color:var(--muted);
    transition:all .15s;white-space:nowrap;
  }
  .btn-resolve:hover { border-color:var(--green);color:var(--green); }

  /* empty states */
  .empty-box {
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    padding:40px;text-align:center;color:var(--muted);font-size:.87rem;
  }
  .empty-box .icon { font-size:2rem;margin-bottom:10px; }

  /* browser notification banner */
  #notif-banner {
    background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);
    border-radius:var(--radius);padding:12px 18px;margin-bottom:16px;
    display:flex;justify-content:space-between;align-items:center;font-size:.83rem;
  }
  #notif-banner.hidden { display:none; }
  .btn-enable-notif {
    padding:5px 14px;border-radius:7px;font-size:.78rem;cursor:pointer;
    background:var(--blue);border:none;color:#fff;transition:opacity .15s;
  }
  .btn-enable-notif:hover { opacity:.85; }
</style>
@endsection

@section('content')
<a href="{{ route('dashboard.show', $device) }}" class="back-link">← Monitor Real-time</a>

<div class="page-title">{{ $device->device_name }}</div>
<div class="page-sub">{{ $device->manufacturer }} {{ $device->model }} &nbsp;·&nbsp; Alert & Notifikasi</div>

<div class="sub-nav">
  <a href="{{ route('dashboard.show', $device) }}">Monitor</a>
  <a href="{{ route('dashboard.diagnosis', $device) }}">Diagnosis</a>
  <a href="{{ route('dashboard.history', $device) }}">Riwayat</a>
  <a href="{{ route('dashboard.alerts', $device) }}" class="active">
    Alerts
    @if($criticalCount > 0)
      <span style="background:var(--red);color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:4px;">{{ $criticalCount }}</span>
    @elseif($warningCount > 0)
      <span style="background:var(--yellow);color:#000;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:4px;">{{ $warningCount }}</span>
    @endif
  </a>
</div>

{{-- Browser notification banner --}}
<div id="notif-banner">
  <span>Aktifkan notifikasi browser untuk mendapatkan alert secara real-time saat halaman ini terbuka.</span>
  <button class="btn-enable-notif" onclick="requestNotifPermission()">Aktifkan Notifikasi</button>
</div>

{{-- Stat bar --}}
<div class="stat-bar">
  <div class="stat-card {{ $criticalCount > 0 ? 'critical' : 'ok' }}">
    <div class="stat-icon" style="font-size:1.2rem;font-weight:700;color:var(--red)">!</div>
    <div class="stat-body">
      <div class="stat-val">{{ $criticalCount }}</div>
      <div class="stat-label">Critical Aktif</div>
    </div>
  </div>
  <div class="stat-card {{ $warningCount > 0 ? 'warning' : 'ok' }}">
    <div class="stat-icon" style="font-size:1.2rem;font-weight:700;color:var(--yellow)">!</div>
    <div class="stat-body">
      <div class="stat-val">{{ $warningCount }}</div>
      <div class="stat-label">Warning Aktif</div>
    </div>
  </div>
  <div class="stat-card ok">
    <div class="stat-icon" style="font-size:1.2rem;font-weight:700;color:var(--green)">✓</div>
    <div class="stat-body">
      <div class="stat-val">{{ $recentResolved->count() }}</div>
      <div class="stat-label">Resolved (24j)</div>
    </div>
  </div>
</div>

{{-- Active alerts --}}
<div class="toolbar">
  <div class="section-title">⚠️ Alert Aktif ({{ $activeAlerts->count() }})</div>
  @if($activeAlerts->isNotEmpty())
    <button class="btn-resolve-all" onclick="resolveAll()">Tandai Semua Selesai</button>
  @endif
</div>

@if($activeAlerts->isEmpty())
  <div class="empty-box" style="margin-bottom:28px;">
    <div class="icon" style="color:var(--green);font-size:1.4rem;font-weight:700">✓</div>
    Tidak ada alert aktif saat ini. Semua komponen dalam kondisi normal.
  </div>
@else
  <div class="alert-list" id="active-list">
    @foreach($activeAlerts as $alert)
      @php
        $icon     = $alert->severity === 'CRITICAL' ? 'C' : 'W';
        $iconColor= $alert->severity === 'CRITICAL' ? 'var(--red)' : 'var(--yellow)';
      @endphp
      <div class="alert-card {{ strtolower($alert->severity) }}" id="alert-{{ $alert->id }}">
        <div class="alert-icon" style="font-size:.75rem;font-weight:700;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.2);color:{{ $iconColor }};flex-shrink:0;margin-top:2px;">{{ $icon }}</div>
        <div class="alert-body">
          <div class="alert-title">{{ $alert->title }}</div>
          @if($alert->message)
            <div class="alert-msg">{{ $alert->message }}</div>
          @endif
          @if(count($alert->suggestions ?? []) > 0)
            <ul class="alert-suggs">
              @foreach($alert->suggestions as $s)
                <li>{{ $s }}</li>
              @endforeach
            </ul>
          @endif
          <div class="alert-meta">
            <span class="badge {{ $alert->severity === 'CRITICAL' ? 'badge-critical' : 'badge-warning' }}">{{ $alert->severity }}</span>
            <span class="alert-time">{{ $alert->created_at->diffForHumans() }} &nbsp;·&nbsp; {{ $alert->created_at->format('H:i:s d M') }}</span>
          </div>
        </div>
        <div class="alert-actions">
          <button class="btn-resolve" onclick="resolveOne({{ $alert->id }})">✓ Selesai</button>
        </div>
      </div>
    @endforeach
  </div>
@endif

{{-- Recently resolved --}}
@if($recentResolved->isNotEmpty())
  <div class="section-title" style="margin-bottom:12px;">Baru Diselesaikan (24 jam)</div>
  <div class="alert-list">
    @foreach($recentResolved as $alert)
      <div class="alert-card resolved">
        <div class="alert-icon" style="font-size:.75rem;font-weight:700;color:var(--muted)">R</div>
        <div class="alert-body">
          <div class="alert-title" style="opacity:.7">{{ $alert->title }}</div>
          @if($alert->message)
            <div class="alert-msg">{{ Str::limit($alert->message, 100) }}</div>
          @endif
          <div class="alert-meta">
            <span class="badge badge-offline">RESOLVED</span>
            <span class="alert-time">Diselesaikan {{ $alert->resolved_at->diffForHumans() }}</span>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection

@section('scripts')
<script>
const DEVICE = {{ $device->id }};
const API    = '/api';

// ── Browser Notification ─────────────────────────────────────────────────────
let notifEnabled = false;

function requestNotifPermission() {
  if (!('Notification' in window)) {
    alert('Browser kamu tidak mendukung notifikasi.');
    return;
  }
  Notification.requestPermission().then(perm => {
    if (perm === 'granted') {
      notifEnabled = true;
      document.getElementById('notif-banner').classList.add('hidden');
      new Notification('DeviceCare', {
        body: 'Notifikasi aktif. Kamu akan menerima alert CRITICAL secara real-time.',
        tag: 'devicecare-ready',
      });
    }
  });
}

// Auto-hide banner jika sudah granted
if (Notification.permission === 'granted') {
  notifEnabled = true;
  document.getElementById('notif-banner').classList.add('hidden');
} else if (Notification.permission === 'denied') {
  document.getElementById('notif-banner').innerHTML =
    '<span>Notifikasi browser diblokir. Aktifkan di pengaturan browser untuk menerima alert.</span>';
}

function showBrowserNotif(title, body, severity) {
  if (!notifEnabled || Notification.permission !== 'granted') return;
  new Notification(title, {
    body,
    tag: 'devicecare-alert',
    requireInteraction: severity === 'CRITICAL',
  });
}

// ── Resolve ───────────────────────────────────────────────────────────────────
async function resolveOne(alertId) {
  const el = document.getElementById('alert-' + alertId);
  if (el) { el.style.opacity = '0.4'; el.style.pointerEvents = 'none'; }
  try {
    await fetch(`${API}/alerts/${alertId}/resolve`, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
    setTimeout(() => el?.remove(), 400);
    updateCounts();
  } catch (e) { if (el) { el.style.opacity = '1'; el.style.pointerEvents = ''; } }
}

async function resolveAll() {
  if (!confirm('Tandai semua alert aktif sebagai selesai?')) return;
  try {
    await fetch(`${API}/devices/${DEVICE}/alerts/resolve-all`, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
    document.querySelectorAll('#active-list .alert-card').forEach(el => el.remove());
    updateCounts();
  } catch (e) { alert('Gagal. Coba lagi.'); }
}

function updateCounts() {
  const remaining = document.querySelectorAll('#active-list .alert-card').length;
  document.querySelector('.toolbar .section-title').textContent = `⚠️ Alert Aktif (${remaining})`;
}

// ── Polling untuk alert baru ─────────────────────────────────────────────────
let lastCheck = new Date().toISOString();

async function pollAlerts() {
  try {
    const r = await fetch(`${API}/devices/${DEVICE}/alerts?since=${lastCheck}`);
    const { data } = await r.json();
    if (!data?.length) return;

    lastCheck = new Date().toISOString();

    data.forEach(alert => {
      // Tambahkan ke daftar aktif
      const sevColor = alert.severity === 'CRITICAL' ? 'var(--red)' : 'var(--yellow)';
      const sevLabel = alert.severity === 'CRITICAL' ? 'C' : 'W';
      const suggs = (alert.suggestions || []).map(s => `<li>${s}</li>`).join('');
      const card = document.createElement('div');
      card.id        = 'alert-' + alert.id;
      card.className = 'alert-card ' + alert.severity.toLowerCase();
      card.innerHTML = `
        <div class="alert-icon" style="font-size:.75rem;font-weight:700;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.2);color:${sevColor};flex-shrink:0;margin-top:2px;">${sevLabel}</div>
        <div class="alert-body">
          <div class="alert-title">${alert.title}</div>
          ${alert.message ? `<div class="alert-msg">${alert.message}</div>` : ''}
          ${suggs ? `<ul class="alert-suggs">${suggs}</ul>` : ''}
          <div class="alert-meta">
            <span class="badge badge-${alert.severity === 'CRITICAL' ? 'critical' : 'warning'}">${alert.severity}</span>
            <span class="alert-time">Baru saja</span>
          </div>
        </div>
        <div class="alert-actions">
          <button class="btn-resolve" onclick="resolveOne(${alert.id})">✓ Selesai</button>
        </div>`;

      const list = document.getElementById('active-list');
      if (list) list.prepend(card);

      // Browser notification
      showBrowserNotif(alert.title, alert.message || '', alert.severity);
    });

    updateCounts();
  } catch (e) { /* silent */ }
}

// Poll setiap 5 detik untuk alert baru
setInterval(pollAlerts, 5000);
</script>
@endsection
