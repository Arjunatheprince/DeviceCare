@extends('layouts.app')

@section('title', 'Riwayat — '.$device->device_name)

@section('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('styles')
<style>
  .back-link { font-size:.82rem;color:var(--muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;transition:color .15s; }
  .back-link:hover { color:var(--text); }

  /* sub-nav */
  .sub-nav { display:flex;gap:4px;margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:0; }
  .sub-nav a {
    padding:8px 16px;font-size:.85rem;color:var(--muted);border-radius:8px 8px 0 0;
    border:1px solid transparent;border-bottom:none;transition:color .15s,background .15s;
    margin-bottom:-1px;
  }
  .sub-nav a:hover { color:var(--text);background:var(--surface2); }
  .sub-nav a.active { color:var(--text);background:var(--surface);border-color:var(--border); }

  /* controls */
  .controls {
    display:flex;gap:12px;flex-wrap:wrap;align-items:center;
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;
  }
  .control-group { display:flex;align-items:center;gap:8px; }
  .control-label { font-size:.78rem;color:var(--muted); }
  .btn-group { display:flex;gap:4px; }
  .btn {
    padding:5px 14px;border-radius:8px;font-size:.8rem;cursor:pointer;
    background:var(--surface2);border:1px solid var(--border);color:var(--muted);
    transition:all .15s;
  }
  .btn:hover { color:var(--text);border-color:var(--blue); }
  .btn.active { background:rgba(59,130,246,.15);border-color:var(--blue);color:var(--blue);font-weight:600; }
  select.ctrl {
    background:var(--surface2);border:1px solid var(--border);color:var(--text);
    padding:5px 10px;border-radius:8px;font-size:.8rem;cursor:pointer;
  }

  /* summary cards */
  .summary-row { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px; }
  .sum-card {
    background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
    padding:14px 16px;text-align:center;
  }
  .sum-card.up-bad   { border-color:var(--red);    background:rgba(239,68,68,.05); }
  .sum-card.up-good  { border-color:var(--green);  background:rgba(34,197,94,.05); }
  .sum-card.down-bad { border-color:var(--yellow); background:rgba(245,158,11,.05); }
  .sum-card.neutral  { border-color:var(--border); }
  .sum-label { font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px; }
  .sum-val   { font-size:1.6rem;font-weight:700; }
  .sum-sub   { font-size:.72rem;color:var(--muted);margin-top:4px; }

  /* trend badge */
  .trend-badge {
    display:inline-flex;align-items:center;gap:4px;
    font-size:.75rem;font-weight:700;padding:2px 10px;border-radius:20px;
  }
  .trend-up-bad   { background:rgba(239,68,68,.15); color:var(--red); }
  .trend-up-good  { background:rgba(34,197,94,.15); color:var(--green); }
  .trend-down-bad { background:rgba(245,158,11,.15);color:var(--yellow); }
  .trend-neutral  { background:rgba(136,146,164,.1);color:var(--muted); }

  /* main chart */
  .main-chart-card {
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:20px;margin-bottom:20px;
  }
  .chart-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:16px; }
  .chart-title  { font-size:.85rem;font-weight:600; }
  .chart-sub    { font-size:.78rem;color:var(--muted);margin-top:2px; }
  #trend-label  { font-size:.82rem;color:var(--muted); }
  canvas.main   { max-height:260px; }

  /* overview grid */
  .overview-title { font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:12px; }
  .overview-grid  { display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px; }
  .ov-card        { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px; }
  .ov-title       { font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px; }
  canvas.ov       { max-height:120px; }

  /* loading */
  #loading-overlay {
    position:fixed;inset:0;background:rgba(15,17,23,.7);
    display:flex;align-items:center;justify-content:center;
    z-index:999;transition:opacity .3s;
  }
  #loading-overlay.hidden { opacity:0;pointer-events:none; }
  .spinner { width:36px;height:36px;border:3px solid var(--border);border-top-color:var(--blue);border-radius:50%;animation:spin 1s linear infinite; }
  @keyframes spin { to{transform:rotate(360deg);} }
</style>
@endsection

@section('content')
<a href="{{ route('dashboard.show', $device) }}" class="back-link">← Monitor Real-time</a>

<div class="page-title">{{ $device->device_name }}</div>
<div class="page-sub">{{ $device->manufacturer }} {{ $device->model }} &nbsp;·&nbsp; Riwayat & Trend Analysis</div>

<div class="sub-nav">
  <a href="{{ route('dashboard.show', $device) }}">Monitor</a>
  <a href="{{ route('dashboard.diagnosis', $device) }}">Diagnosis</a>
  <a href="{{ route('dashboard.history', $device) }}" class="active">Riwayat</a>
  <a href="{{ route('dashboard.alerts', $device) }}">Alerts</a>
</div>

{{-- Controls --}}
<div class="controls">
  <div class="control-group">
    <span class="control-label">Periode:</span>
    <div class="btn-group" id="days-group">
      <button class="btn" data-days="1">24 Jam</button>
      <button class="btn active" data-days="7">7 Hari</button>
      <button class="btn" data-days="14">14 Hari</button>
      <button class="btn" data-days="30">30 Hari</button>
    </div>
  </div>
  <div class="control-group">
    <span class="control-label">Metrik:</span>
    <select class="ctrl" id="metric-select">
      <option value="health_score" selected>Health Score</option>
      <option value="cpu_usage">CPU Usage %</option>
      <option value="cpu_temperature">CPU Temperature °C</option>
      <option value="ram_usage">RAM Usage %</option>
      <option value="disk_usage">Disk Usage %</option>
      <option value="battery_percentage">Battery %</option>
      <option value="gpu_temperature">GPU Temperature °C</option>
    </select>
  </div>
  <div class="control-group">
    <span class="control-label">Granularitas:</span>
    <div class="btn-group" id="period-group">
      <button class="btn" data-period="hour">Per Jam</button>
      <button class="btn active" data-period="day">Per Hari</button>
    </div>
  </div>
</div>

{{-- Summary cards --}}
<div class="summary-row" id="summary-row">
  <div class="sum-card neutral">
    <div class="sum-label">Rata-rata</div>
    <div class="sum-val" id="s-avg">—</div>
    <div class="sum-sub">periode ini</div>
  </div>
  <div class="sum-card neutral">
    <div class="sum-label">Minimum</div>
    <div class="sum-val" id="s-min">—</div>
    <div class="sum-sub">terendah</div>
  </div>
  <div class="sum-card neutral">
    <div class="sum-label">Maksimum</div>
    <div class="sum-val" id="s-max">—</div>
    <div class="sum-sub">tertinggi</div>
  </div>
  <div class="sum-card neutral" id="s-trend-card">
    <div class="sum-label">Trend</div>
    <div class="sum-val" id="s-trend">—</div>
    <div class="sum-sub" id="s-trend-sub">dibanding awal periode</div>
  </div>
</div>

{{-- Main chart --}}
<div class="main-chart-card">
  <div class="chart-header">
    <div>
      <div class="chart-title" id="chart-main-title">Health Score — 7 Hari Terakhir</div>
      <div class="chart-sub" id="trend-label">Memuat data...</div>
    </div>
    <span class="trend-badge trend-neutral" id="trend-badge">—</span>
  </div>
  <canvas class="main" id="chart-main"></canvas>
</div>

{{-- Overview mini charts --}}
<div class="overview-title">Overview Semua Metrik</div>
<div class="overview-grid">
  <div class="ov-card"><div class="ov-title">Health Score</div><canvas class="ov" id="ov-health"></canvas></div>
  <div class="ov-card"><div class="ov-title">CPU Usage %</div><canvas class="ov" id="ov-cpu"></canvas></div>
  <div class="ov-card"><div class="ov-title">RAM Usage %</div><canvas class="ov" id="ov-ram"></canvas></div>
  <div class="ov-card"><div class="ov-title">GPU Temperature °C</div><canvas class="ov" id="ov-gpu"></canvas></div>
  <div class="ov-card"><div class="ov-title">Disk Usage %</div><canvas class="ov" id="ov-disk"></canvas></div>
  <div class="ov-card"><div class="ov-title">Battery %</div><canvas class="ov" id="ov-battery"></canvas></div>
</div>

<div id="loading-overlay"><div class="spinner"></div></div>
@endsection

@section('scripts')
<script>
const DEVICE = {{ $device->id }};
const API    = '/api';

// ── state ──────────────────────────────────────────────────────────────────
let state = { days: 7, metric: 'health_score', period: 'day' };

// ── chart instances ─────────────────────────────────────────────────────────
const BASE_OPTS = {
  responsive: true, maintainAspectRatio: true, animation: { duration: 400 },
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { color: '#8892a4', font: { size: 9 }, maxTicksLimit: 7 }, grid: { color: '#2d3148' } },
    y: { ticks: { color: '#8892a4', font: { size: 9 } }, grid: { color: '#2d3148' }, min: 0 },
  },
};

function mkChart(id, color, opts = {}) {
  const ctx = document.getElementById(id).getContext('2d');
  return new Chart(ctx, {
    type: 'line',
    data: { labels: [], datasets: [{ data: [], borderColor: color, backgroundColor: color.replace(')', ', 0.12)').replace('rgb', 'rgba'), tension: .35, pointRadius: 0, fill: true }] },
    options: { ...JSON.parse(JSON.stringify(BASE_OPTS)), ...opts },
  });
}

const mainChart = (() => {
  const ctx = document.getElementById('chart-main').getContext('2d');
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [
        { label: 'Rata-rata', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', tension: .35, pointRadius: 3, fill: true },
        { label: 'Max',       data: [], borderColor: '#22c55e', backgroundColor: 'transparent', tension: .35, pointRadius: 0, borderDash: [4,3] },
        { label: 'Min',       data: [], borderColor: '#ef4444', backgroundColor: 'transparent', tension: .35, pointRadius: 0, borderDash: [4,3] },
      ],
    },
    options: {
      ...JSON.parse(JSON.stringify(BASE_OPTS)),
      plugins: { legend: { display: true, labels: { color: '#8892a4', boxWidth: 10, font: { size: 10 } } } },
      scales: {
        x: { ...JSON.parse(JSON.stringify(BASE_OPTS.scales.x)), ticks: { ...BASE_OPTS.scales.x.ticks, maxTicksLimit: 8 } },
        y: { ...JSON.parse(JSON.stringify(BASE_OPTS.scales.y)) },
      },
    },
  });
})();

const ovCharts = {
  health:  mkChart('ov-health',  '#22c55e'),
  cpu:     mkChart('ov-cpu',     '#3b82f6'),
  ram:     mkChart('ov-ram',     '#a855f7'),
  gpu:     mkChart('ov-gpu',     '#f59e0b'),
  disk:    mkChart('ov-disk',    '#ef4444'),
  battery: mkChart('ov-battery', '#06b6d4'),
};

// ── helpers ─────────────────────────────────────────────────────────────────
const $  = id => document.getElementById(id);
const fmt = (v, u = '') => v != null ? parseFloat(v).toFixed(1) + u : '—';

function fmtLabel(ts, period) {
  const d = new Date(ts);
  if (period === 'hour') return d.toLocaleTimeString('id', { hour: '2-digit', minute: '2-digit' });
  return d.toLocaleDateString('id', { day: 'numeric', month: 'short' });
}

function unit(metric) {
  if (metric.includes('temperature')) return '°C';
  if (metric.includes('kbps')) return ' KB/s';
  return '%';
}

function trendClass(trend, metric) {
  if (trend === null || Math.abs(trend) < 2) return 'trend-neutral';
  const higherGood = ['health_score', 'battery_percentage'].includes(metric);
  if (trend > 0) return higherGood ? 'trend-up-good' : 'trend-up-bad';
  return higherGood ? 'trend-down-bad' : 'trend-up-good';
}

function loading(show) {
  $('loading-overlay').classList.toggle('hidden', !show);
}

// ── data fetchers ────────────────────────────────────────────────────────────
async function loadMain() {
  loading(true);
  try {
    const r = await fetch(`${API}/devices/${DEVICE}/history?metric=${state.metric}&days=${state.days}&period=${state.period}`);
    const d = await r.json();

    const labels = d.data.map(p => fmtLabel(p.ts, state.period));
    mainChart.data.labels                   = labels;
    mainChart.data.datasets[0].data         = d.data.map(p => p.avg);
    mainChart.data.datasets[1].data         = d.data.map(p => p.max);
    mainChart.data.datasets[2].data         = d.data.map(p => p.min);
    mainChart.update();

    // Summary
    const u = unit(state.metric);
    $('s-avg').textContent = fmt(d.summary.avg, u);
    $('s-min').textContent = fmt(d.summary.min, u);
    $('s-max').textContent = fmt(d.summary.max, u);

    const trend = d.summary.trend;
    $('s-trend').textContent    = trend != null ? (trend > 0 ? '+' : '') + trend + '%' : '—';
    $('trend-label').textContent = d.summary.trend_label;

    const cls = trendClass(trend, state.metric);
    const badge = $('trend-badge');
    badge.className = 'trend-badge ' + cls;
    badge.textContent = trend != null ? (trend > 0 ? '▲ ' : '▼ ') + Math.abs(trend) + '%' : 'Stabil';

    $('chart-main-title').textContent =
      document.querySelector(`#metric-select option[value="${state.metric}"]`)?.textContent +
      ' — ' + state.days + ' Hari Terakhir';

  } catch (e) { console.error(e); }
  loading(false);
}

async function loadOverview() {
  try {
    const r = await fetch(`${API}/devices/${DEVICE}/history/multi?days=${state.days}`);
    const { data } = await r.json();
    if (!data?.length) return;

    const labels = data.map(d => fmtLabel(d.day + 'T00:00:00', 'day'));
    const set = (chart, key) => {
      chart.data.labels           = labels;
      chart.data.datasets[0].data = data.map(d => d[key] ?? null);
      chart.update('none');
    };
    set(ovCharts.health,  'health_score');
    set(ovCharts.cpu,     'cpu_usage');
    set(ovCharts.ram,     'ram_usage');
    set(ovCharts.gpu,     'gpu_temp');
    set(ovCharts.disk,    'disk_usage');
    set(ovCharts.battery, 'battery');
  } catch (e) { console.error(e); }
}

async function refresh() {
  await Promise.all([loadMain(), loadOverview()]);
}

// ── event listeners ──────────────────────────────────────────────────────────
document.querySelectorAll('#days-group .btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#days-group .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.days = parseInt(btn.dataset.days);
    refresh();
  });
});

document.querySelectorAll('#period-group .btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#period-group .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.period = btn.dataset.period;
    refresh();
  });
});

$('metric-select').addEventListener('change', e => {
  state.metric = e.target.value;
  loadMain();
});

// ── init ──────────────────────────────────────────────────────────────────────
refresh();
</script>
@endsection
