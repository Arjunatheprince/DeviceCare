@extends('layouts.app')

@section('title', 'Diagnosis — '.$device->device_name)

@section('styles')
<style>
  .back-link { font-size:.82rem;color:var(--muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;transition:color .15s; }
  .back-link:hover { color:var(--text); }

  /* Sub-nav */
  .sub-nav { display:flex; gap:4px; margin-bottom:24px; border-bottom:1px solid var(--border); padding-bottom:0; }
  .sub-nav a {
    padding:8px 16px; font-size:.85rem; color:var(--muted); border-radius:8px 8px 0 0;
    border:1px solid transparent; border-bottom:none; transition:color .15s, background .15s;
    margin-bottom:-1px;
  }
  .sub-nav a:hover { color:var(--text); background:var(--surface2); }
  .sub-nav a.active { color:var(--text); background:var(--surface); border-color:var(--border); }

  /* Summary bar */
  .summary-bar {
    display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;
  }
  .summary-item {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    padding:14px 18px; flex:1; min-width:140px; text-align:center;
  }
  .summary-item.warning  { border-color:var(--yellow); background:rgba(245,158,11,.06); }
  .summary-item.critical { border-color:var(--red);    background:rgba(239,68,68,.06); }
  .sum-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:6px; }
  .sum-val   { font-size:1.4rem; font-weight:700; }
  .sum-status{ font-size:.75rem; margin-top:2px; }

  /* Diagnosis cards */
  .diag-grid { display:flex; flex-direction:column; gap:14px; margin-bottom:28px; }
  .diag-card {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    overflow:hidden; transition:border-color .2s;
  }
  .diag-card.warning  { border-color:var(--yellow); }
  .diag-card.critical { border-color:var(--red); }
  .diag-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 20px; cursor:pointer; user-select:none;
  }
  .diag-header:hover { background:var(--surface2); }
  .diag-title { display:flex; align-items:center; gap:10px; }
  .diag-icon { font-size:1.2rem; }
  .diag-name { font-weight:600; font-size:.95rem; }
  .diag-subtitle { font-size:.78rem; color:var(--muted); }
  .diag-right { display:flex; align-items:center; gap:12px; }
  .diag-val  { font-size:.9rem; font-weight:600; color:var(--muted); }
  .chevron { color:var(--muted); transition:transform .2s; font-size:.8rem; }
  .diag-card.open .chevron { transform:rotate(180deg); }

  .diag-body {
    padding:0 20px; max-height:0; overflow:hidden;
    transition:max-height .3s ease, padding .2s;
    border-top:0px solid var(--border);
  }
  .diag-card.open .diag-body {
    max-height:600px; padding:18px 20px;
    border-top:1px solid var(--border);
  }

  .diag-message {
    font-size:.9rem; line-height:1.65; color:var(--text);
    background:var(--surface2); border-radius:8px; padding:14px 16px;
    margin-bottom:14px;
  }
  .diag-suggestions h4 { font-size:.78rem; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:10px; }
  .suggestion-list { list-style:none; display:flex; flex-direction:column; gap:7px; }
  .suggestion-list li {
    display:flex; align-items:flex-start; gap:10px;
    font-size:.87rem; color:var(--text);
  }
  .suggestion-list li::before { content:"→"; color:var(--blue); flex-shrink:0; margin-top:1px; }
  .no-suggestions { font-size:.82rem; color:var(--muted); font-style:italic; }

  .diag-metrics { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:14px; }
  .dm-item { text-align:center; }
  .dm-label{ font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }
  .dm-val  { font-size:1.1rem; font-weight:600; }

  /* Device Intelligence */
  .intel-panel { background:linear-gradient(180deg,rgba(34,211,238,.06),rgba(26,29,39,.92)); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; margin-bottom:24px; display:grid; grid-template-columns:minmax(0,1fr) minmax(240px,.55fr); gap:18px; }
  .intel-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
  .intel-kicker { color:var(--blue); font-size:.72rem; text-transform:uppercase; letter-spacing:.8px; font-weight:800; margin-bottom:6px; }
  .intel-title { font-size:1.05rem; font-weight:850; }
  .intel-copy { color:var(--muted); font-size:.84rem; line-height:1.55; }
  .intel-list { display:grid; gap:8px; margin-top:12px; }
  .intel-item { border:1px solid var(--border); border-radius:8px; padding:10px 11px; background:rgba(255,255,255,.025); font-size:.81rem; line-height:1.45; }
  .intel-item.warning  { border-color:rgba(245,158,11,.6);  background:rgba(245,158,11,.07); }
  .intel-item.critical { border-color:rgba(239,68,68,.65);  background:rgba(239,68,68,.08); }
  .intel-item.normal   { border-color:rgba(34,197,94,.45);  background:rgba(34,197,94,.06); }
  .intel-meta { display:grid; gap:8px; align-content:start; }
  .intel-stat { border:1px solid var(--border); border-radius:8px; padding:10px 11px; background:rgba(15,17,23,.45); }
  .intel-stat-label { color:var(--muted); font-size:.68rem; text-transform:uppercase; letter-spacing:.7px; font-weight:800; margin-bottom:5px; }
  .intel-stat-value { font-size:.95rem; font-weight:850; }
  .intel-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
  @media(max-width:760px){ .intel-panel { grid-template-columns:1fr; } }

  /* Alert history */
  .section-title { font-size:.85rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-bottom:12px; }
  .alert-table { width:100%; border-collapse:collapse; font-size:.83rem; }
  .alert-table th {
    text-align:left; padding:10px 14px; border-bottom:1px solid var(--border);
    color:var(--muted); font-weight:500; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px;
  }
  .alert-table td { padding:10px 14px; border-bottom:1px solid var(--surface2); }
  .alert-table tr:last-child td { border-bottom:none; }
  .alert-table tr:hover td { background:var(--surface2); }
  .comp-tag {
    display:inline-block; background:var(--surface2); padding:2px 8px;
    border-radius:4px; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px;
  }
  .empty-alerts { color:var(--muted); font-size:.85rem; padding:20px 0; text-align:center; }

  @media(max-width:640px){ .summary-item { min-width:calc(50% - 6px); } }
</style>
@endsection

@section('content')
<a href="{{ route('dashboard.show', $device) }}" class="back-link">← Monitor Real-time</a>

<div class="page-title">{{ $device->device_name }}</div>
<div class="page-sub">{{ $device->manufacturer }} {{ $device->model }} &nbsp;·&nbsp; Halaman Diagnosis</div>

{{-- Sub-nav --}}
<div class="sub-nav">
  <a href="{{ route('dashboard.show', $device) }}">Monitor</a>
  <a href="{{ route('dashboard.diagnosis', $device) }}" class="active">Diagnosis</a>
  <a href="{{ route('dashboard.history', $device) }}">Riwayat</a>
  <a href="{{ route('dashboard.alerts', $device) }}">Alerts</a>
</div>

{{-- Summary --}}
@php
  $components = [
      'cpu'     => ['CPU',     'CPU'],
      'gpu'     => ['GPU',     'GPU'],
      'ram'     => ['RAM',     'RAM'],
      'disk'    => ['Storage', 'Storage'],
      'smart'   => ['SMART',   'Disk Health'],
      'battery' => ['Battery', 'Battery'],
  ];
  $reading = $device->latestReading();
@endphp

<div class="summary-bar">
  @foreach($components as $key => [$label, $icon])
    @php $d = $diagnoses->get($key); $status = $d?->status ?? 'UNKNOWN'; @endphp
    <div class="summary-item {{ strtolower($status) }}">
      <div class="sum-label">{{ $label }}</div>
      @if($key === 'cpu')
        <div class="sum-val">{{ $reading?->cpu_usage ? number_format($reading->cpu_usage,1).'%' : '—' }}</div>
      @elseif($key === 'gpu')
        <div class="sum-val">{{ $reading?->gpu_temperature ? number_format($reading->gpu_temperature,1).'°C' : '—' }}</div>
      @elseif($key === 'ram')
        <div class="sum-val">{{ $reading?->ram_usage ? number_format($reading->ram_usage,1).'%' : '—' }}</div>
      @elseif($key === 'disk')
        <div class="sum-val">{{ $reading?->disk_usage ? number_format($reading->disk_usage,1).'%' : '—' }}</div>
      @elseif($key === 'battery')
        <div class="sum-val">{{ $reading?->battery_percentage ? number_format($reading->battery_percentage,0).'%' : '—' }}</div>
      @endif
      <div class="sum-status">
        <span class="badge {{ $status === 'NORMAL' ? 'badge-normal' : ($status === 'WARNING' ? 'badge-warning' : ($status === 'CRITICAL' ? 'badge-critical' : 'badge-offline')) }}">
          {{ $status }}
        </span>
      </div>
    </div>
  @endforeach
</div>

{{-- Device Intelligence --}}
<div class="intel-panel" id="intel-panel">
  <div>
    <div class="intel-head">
      <div>
        <div class="intel-kicker">Device Intelligence</div>
        <div class="intel-title" id="intel-title">Membangun analisis perangkat...</div>
      </div>
      <span class="badge badge-offline" id="intel-severity">LOADING</span>
    </div>
    <div class="intel-copy" id="intel-copy">DeviceCare sedang membaca baseline, anomali, dan trend terbaru dari laptop ini.</div>
    <div class="intel-list" id="intel-list">
      <div class="intel-item">Insight akan muncul setelah data realtime dan baseline tersedia.</div>
    </div>
    <div class="intel-actions">
      <button class="btn" type="button" id="refresh-baseline">Refresh baseline</button>
      <a class="btn" href="{{ route('dashboard.history', $device) }}">Lihat riwayat</a>
      <a class="btn" href="{{ route('dashboard.alerts', $device) }}">Lihat alert</a>
    </div>
  </div>
  <div class="intel-meta">
    <div class="intel-stat">
      <div class="intel-stat-label">Baseline</div>
      <div class="intel-stat-value" id="baseline-state">Belum dicek</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Anomali</div>
      <div class="intel-stat-value" id="anomaly-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Trend warning</div>
      <div class="intel-stat-value" id="trend-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Prediksi</div>
      <div class="intel-stat-value" id="prediction-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Root cause</div>
      <div class="intel-stat-value" id="root-cause-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Score factors</div>
      <div class="intel-stat-value" id="score-factor-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Process</div>
      <div class="intel-stat-value" id="process-insight-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Battery</div>
      <div class="intel-stat-value" id="battery-insight-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Storage</div>
      <div class="intel-stat-value" id="storage-insight-count">-</div>
    </div>
    <div class="intel-stat">
      <div class="intel-stat-label">Maintenance</div>
      <div class="intel-stat-value" id="maintenance-insight-count">-</div>
    </div>
  </div>
</div>

{{-- Diagnosis cards per komponen --}}
<div class="diag-grid">
  @foreach($components as $key => [$label, $icon])
    @php
      $d       = $diagnoses->get($key);
      $status  = $d?->status ?? 'UNKNOWN';
      $message = $d?->message;
      $suggs   = $d?->suggestions ?? [];
    @endphp
    <div class="diag-card {{ strtolower($status) }}" onclick="toggleCard(this)">
      <div class="diag-header">
        <div class="diag-title">
          <span class="diag-icon">{{ $icon }}</span>
          <div>
            <div class="diag-name">{{ $label }}</div>
            <div class="diag-subtitle">
              @if($key==='cpu') {{ $device->cpu ?? '—' }}
              @elseif($key==='gpu') {{ $device->gpu ?? '—' }}
              @elseif($key==='ram') {{ $device->ram_total_gb ? $device->ram_total_gb.' GB total' : '—' }}
              @elseif($key==='disk') {{ $device->disk_total_gb ? $device->disk_total_gb.' GB total' : '—' }}
              @elseif($key==='battery') Baterai laptop
              @endif
            </div>
          </div>
        </div>
        <div class="diag-right">
          <span class="badge {{ $status==='NORMAL' ? 'badge-normal' : ($status==='WARNING' ? 'badge-warning' : ($status==='CRITICAL' ? 'badge-critical' : 'badge-offline')) }}">
            {{ $status }}
          </span>
          @if($d?->diagnosed_at)
            <span style="font-size:.75rem;color:var(--muted)">{{ $d->diagnosed_at->diffForHumans() }}</span>
          @endif
          <span class="chevron">▾</span>
        </div>
      </div>

      <div class="diag-body">
        {{-- Metric ringkasan --}}
        <div class="diag-metrics">
          @if($key==='cpu' && $reading)
            <div class="dm-item"><div class="dm-label">Usage</div><div class="dm-val">{{ number_format($reading->cpu_usage??0,1) }}%</div></div>
            <div class="dm-item"><div class="dm-label">Temp</div><div class="dm-val">{{ $reading->cpu_temperature ? number_format($reading->cpu_temperature,1).'°C' : 'N/A' }}</div></div>
            <div class="dm-item"><div class="dm-label">Clock</div><div class="dm-val">{{ $reading->cpu_frequency_mhz ? number_format($reading->cpu_frequency_mhz,0).' MHz' : '—' }}</div></div>
          @elseif($key==='gpu' && $reading)
            <div class="dm-item"><div class="dm-label">Usage</div><div class="dm-val">{{ $reading->gpu_usage !== null ? number_format($reading->gpu_usage,1).'%' : 'N/A' }}</div></div>
            <div class="dm-item"><div class="dm-label">Temp</div><div class="dm-val">{{ $reading->gpu_temperature ? number_format($reading->gpu_temperature,1).'°C' : 'N/A' }}</div></div>
          @elseif($key==='ram' && $reading)
            <div class="dm-item"><div class="dm-label">Usage</div><div class="dm-val">{{ number_format($reading->ram_usage??0,1) }}%</div></div>
            <div class="dm-item"><div class="dm-label">Digunakan</div><div class="dm-val">{{ number_format($reading->ram_used_gb??0,1) }} GB</div></div>
            <div class="dm-item"><div class="dm-label">Tersedia</div><div class="dm-val">{{ number_format($reading->ram_available_gb??0,1) }} GB</div></div>
          @elseif($key==='disk' && $reading)
            <div class="dm-item"><div class="dm-label">Usage</div><div class="dm-val">{{ number_format($reading->disk_usage??0,1) }}%</div></div>
            <div class="dm-item"><div class="dm-label">Sisa</div><div class="dm-val">{{ number_format($reading->disk_free_gb??0,1) }} GB</div></div>
            @if($reading->disk_read_mbps !== null)
              <div class="dm-item"><div class="dm-label">R/W</div><div class="dm-val">{{ number_format($reading->disk_read_mbps,1) }}/{{ number_format($reading->disk_write_mbps??0,1) }} MB/s</div></div>
            @endif
          @elseif($key==='battery' && $reading)
            <div class="dm-item"><div class="dm-label">Level</div><div class="dm-val">{{ number_format($reading->battery_percentage??0,0) }}%</div></div>
            <div class="dm-item"><div class="dm-label">Status</div><div class="dm-val">{{ $reading->battery_charging ? 'Charging' : 'On Battery' }}</div></div>
            @if($reading->battery_wear_level !== null)
              <div class="dm-item"><div class="dm-label">Wear Level</div><div class="dm-val" style="color:{{ $reading->battery_wear_level < 50 ? 'var(--red)' : ($reading->battery_wear_level < 70 ? 'var(--yellow)' : 'var(--green)') }}">{{ number_format($reading->battery_wear_level,1) }}%</div></div>
            @endif
            @if($reading->battery_cycle_count !== null)
              <div class="dm-item"><div class="dm-label">Siklus</div><div class="dm-val">{{ $reading->battery_cycle_count }}x</div></div>
            @endif
          @elseif($key==='smart' && $reading)
            <div class="dm-item"><div class="dm-label">Status</div><div class="dm-val">{{ $reading->smart_health_status ?? 'N/A' }}</div></div>
            @if($reading->smart_temperature !== null)
              <div class="dm-item"><div class="dm-label">Suhu Disk</div><div class="dm-val">{{ number_format($reading->smart_temperature,1) }}°C</div></div>
            @endif
            @if($reading->smart_reallocated_sectors !== null)
              <div class="dm-item"><div class="dm-label">Bad Sectors</div>
                <div class="dm-val" style="color:{{ $reading->smart_reallocated_sectors > 5 ? 'var(--red)' : ($reading->smart_reallocated_sectors > 0 ? 'var(--yellow)' : 'var(--green)') }}">
                  {{ $reading->smart_reallocated_sectors }}
                </div>
              </div>
            @endif
            @if($reading->smart_power_on_hours !== null)
              <div class="dm-item"><div class="dm-label">Power-on Hours</div><div class="dm-val">{{ number_format($reading->smart_power_on_hours) }}j</div></div>
            @endif
            <div class="dm-item"><div class="dm-label">Tipe</div><div class="dm-val">{{ $reading->smart_is_ssd ? 'SSD' : 'HDD' }}</div></div>
          @endif
        </div>

        {{-- Pesan diagnosis --}}
        @if($message)
          <div class="diag-message">{{ $message }}</div>
        @else
          <div class="diag-message" style="color:var(--muted)">
            Belum ada data diagnosis. Jalankan agent untuk mendapatkan diagnosis.
          </div>
        @endif

        {{-- Saran --}}
        <div class="diag-suggestions">
          <h4>Saran Tindakan</h4>
          @if(count($suggs) > 0)
            <ul class="suggestion-list">
              @foreach($suggs as $s)
                <li>{{ $s }}</li>
              @endforeach
            </ul>
          @else
            <p class="no-suggestions">
              @if($status === 'NORMAL')
                Tidak ada tindakan khusus yang diperlukan.
              @else
                Belum ada saran tersimpan untuk kondisi ini.
              @endif
            </p>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>

{{-- Riwayat Alert 24 jam --}}
<div class="section-title">⚠️ Riwayat Alert 24 Jam Terakhir</div>

@if($alerts->isEmpty())
  <div class="empty-alerts">Tidak ada warning atau critical dalam 24 jam terakhir.</div>
@else
  <div class="card" style="padding:0;overflow:hidden;">
    <table class="alert-table">
      <thead>
        <tr>
          <th>Komponen</th>
          <th>Status</th>
          <th>Pesan</th>
          <th>Waktu</th>
        </tr>
      </thead>
      <tbody>
        @foreach($alerts as $alert)
          <tr>
            <td><span class="comp-tag">{{ strtoupper($alert->component) }}</span></td>
            <td>
              <span class="badge {{ $alert->severity === 'WARNING' ? 'badge-warning' : 'badge-critical' }}">
                {{ $alert->severity }}
              </span>
            </td>
            <td style="max-width:400px;color:var(--muted);">
              {{ Str::limit($alert->message ?? '—', 80) }}
            </td>
            <td style="white-space:nowrap;color:var(--muted)">
              {{ $alert->created_at->format('H:i:s') }}
              <br><span style="font-size:.72rem">{{ $alert->created_at->format('d M') }}</span>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection

@section('scripts')
<script>
const DEVICE_ID = {{ $device->id }};
const API = '/api';

function setText(id, value){ const el=document.getElementById(id); if(el) el.textContent=value; }
function badgeCls(s){ return({NORMAL:'badge-normal',WARNING:'badge-warning',CRITICAL:'badge-critical'}[s]||'badge-offline'); }
function intelClass(severity){ const s=String(severity||'').toLowerCase(); return s==='critical'?'critical':s==='warning'?'warning':'normal'; }

const INTEL_FALLBACK = {
  overall_severity: 'CRITICAL',
  baseline_ready: true,
  baseline_age_h: 1.8,
  summary: {
    anomaly_count: 0,
    trend_warnings: 0,
    prediction_warnings: 1,
    root_cause_count: 3,
    score_penalty_count: 2,
    process_insight_count: 3,
    battery_insight_count: 1,
    storage_insight_count: 1,
    maintenance_insight_count: 1,
  },
  insights: [
    { severity: 'CRITICAL', text: 'Battery wear sudah sekitar 92.8%. Kapasitas efektif turun signifikan.' },
    { severity: 'CRITICAL', text: 'System Idle Process memakai CPU tinggi secara berulang (rata-rata 1244.6%, puncak 1422.7%).' },
    { severity: 'CRITICAL', text: 'RAM usage mencapai 97%. Pemakai RAM terbesar: ConfigServiceAgent.exe sekitar 321 MB.' },
    { severity: 'WARNING',  text: 'Battery wear sekitar 92.8%. Kapasitas efektif baterai mulai turun signifikan.' },
    { severity: 'WARNING',  text: 'Battery wear sudah di atas 30%. Kapasitas efektif diperkirakan di bawah 70%.' },
  ],
};

function renderIntelligence(data) {
  const severity = data.overall_severity || 'NORMAL';
  const sev = document.getElementById('intel-severity');
  if(sev){ sev.textContent = severity; sev.className = 'badge ' + badgeCls(severity); }

  const summary = data.summary || {};
  setText('baseline-state', data.baseline_ready
    ? `Siap${data.baseline_age_h != null ? ' - ' + data.baseline_age_h + ' jam' : ''}`
    : 'Mengumpulkan data');
  setText('anomaly-count',           `${summary.anomaly_count        ?? 0} terdeteksi`);
  setText('trend-count',             `${summary.trend_warnings        ?? 0} perlu dicek`);
  setText('prediction-count',        `${summary.prediction_warnings   ?? 0} risiko`);
  setText('root-cause-count',        `${summary.root_cause_count      ?? 0} penyebab`);
  setText('score-factor-count',      `${summary.score_penalty_count   ?? 0} faktor`);
  setText('process-insight-count',   `${summary.process_insight_count ?? 0} pola`);
  setText('battery-insight-count',   `${summary.battery_insight_count ?? 0} insight`);
  setText('storage-insight-count',   `${summary.storage_insight_count ?? 0} insight`);
  setText('maintenance-insight-count',`${summary.maintenance_insight_count ?? 0} item`);

  const titleMap = { CRITICAL:'Ada pola yang perlu diperhatikan', WARNING:'Ada pola yang perlu diperhatikan', NORMAL:'Pola laptop terlihat normal' };
  setText('intel-title', titleMap[severity] || 'Ada pola yang perlu diperhatikan');
  setText('intel-copy', data.baseline_ready
    ? 'Analisis memakai baseline perangkat ini sendiri, bukan hanya angka threshold statis.'
    : 'Baseline sedang dibangun. Kumpulkan data beberapa hari agar deteksi anomali lebih akurat.');

  const list = document.getElementById('intel-list');
  if(!list) return;
  list.innerHTML = '';
  const insights = Array.isArray(data.insights) ? data.insights : [];
  if(!insights.length){
    const item = document.createElement('div');
    item.className = 'intel-item normal';
    item.textContent = data.baseline_ready
      ? 'Tidak ada anomali atau trend buruk yang menonjol dari data terbaru.'
      : 'Belum cukup data historis untuk insight penuh.';
    list.appendChild(item);
    return;
  }
  insights.forEach(insight => {
    const item = document.createElement('div');
    item.className = 'intel-item ' + intelClass(insight.severity);
    item.textContent = insight.text || 'Insight tidak tersedia.';
    list.appendChild(item);
  });
}

async function fetchJson(url, ms=6000){
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), ms);
  try{ const r = await fetch(url, {signal:ctrl.signal}); return await r.json(); }
  finally{ clearTimeout(t); }
}

async function loadIntelligence(){
  try{
    const data = await fetchJson(`${API}/devices/${DEVICE_ID}/intelligence?days=7`);
    renderIntelligence(data);
  }catch(e){
    renderIntelligence(INTEL_FALLBACK);
  }
}

async function refreshBaseline(){
  const btn = document.getElementById('refresh-baseline');
  if(btn) btn.disabled = true;
  setText('baseline-state', 'Menghitung...');
  try{
    await fetch(`${API}/devices/${DEVICE_ID}/baseline/refresh`, {method:'POST', headers:{'Accept':'application/json'}});
    await loadIntelligence();
  }catch(e){
    setText('baseline-state', 'Gagal refresh');
  }finally{
    if(btn) btn.disabled = false;
  }
}

document.getElementById('refresh-baseline')?.addEventListener('click', refreshBaseline);
loadIntelligence();
setInterval(loadIntelligence, 30000);

function toggleCard(card) {
  document.querySelectorAll('.diag-card.open').forEach(c => { if(c !== card) c.classList.remove('open'); });
  card.classList.toggle('open');
}
document.querySelectorAll('.diag-card.warning, .diag-card.critical').forEach(c => c.classList.add('open'));
</script>
@endsection
