@extends('layouts.app')

@section('title', $device->device_name)

@section('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('styles')
<style>
  .back-link { font-size:.82rem; color:var(--muted); display:inline-flex; align-items:center; gap:6px; margin-bottom:18px; }
  .back-link:hover { color:var(--text); }
  .sub-nav { display:flex; gap:4px; margin-bottom:18px; border-bottom:1px solid var(--border); overflow-x:auto; }
  .sub-nav a { padding:8px 14px; font-size:.84rem; color:var(--muted); border-radius:8px 8px 0 0; border:1px solid transparent; border-bottom:none; white-space:nowrap; }
  .sub-nav a:hover { color:var(--text); background:var(--surface2); }
  .sub-nav a.active { color:var(--text); background:var(--surface); border-color:var(--border); }

  .device-top { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; gap:14px; align-items:flex-start; flex-wrap:wrap; }
  .device-name { font-size:1.16rem; font-weight:750; margin-bottom:4px; }
  .device-meta { color:var(--muted); font-size:.83rem; line-height:1.45; }
  .top-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
  .last-update { color:var(--muted); font-size:.78rem; }

  .check-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:16px; display:grid; grid-template-columns:minmax(0,1.35fr) minmax(220px,.65fr); gap:18px; }
  .check-kicker { color:var(--blue); font-size:.72rem; text-transform:uppercase; letter-spacing:.8px; font-weight:750; margin-bottom:7px; }
  .check-title { font-size:1.12rem; font-weight:800; margin-bottom:6px; }
  .check-copy { color:var(--muted); font-size:.86rem; line-height:1.6; }
  .actions { display:flex; gap:9px; flex-wrap:wrap; margin-top:14px; }
  .btn { border:1px solid var(--border); background:var(--surface2); color:var(--text); padding:8px 12px; border-radius:8px; font-size:.82rem; cursor:pointer; display:inline-block; }
  .btn.primary { background:var(--blue); border-color:var(--blue); color:#fff; }
  .btn:hover { border-color:var(--blue); }
  .score-card { border:1px solid var(--border); border-radius:10px; padding:14px; background:rgba(255,255,255,.015); display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .score-num { font-size:2.15rem; line-height:1; font-weight:850; }
  .score-label { color:var(--muted); font-size:.78rem; margin-top:4px; }

  .issue-list { display:flex; flex-direction:column; gap:8px; margin-top:10px; }
  .issue-item { border:1px solid var(--border); border-radius:8px; padding:9px 11px; font-size:.81rem; line-height:1.45; }
  .issue-item.warning { border-color:var(--yellow); background:rgba(245,158,11,.06); }
  .issue-item.critical { border-color:var(--red); background:rgba(239,68,68,.06); }
  .issue-head { display:flex; justify-content:space-between; gap:10px; margin-bottom:3px; font-weight:700; }

  .metric-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; margin-bottom:16px; }
  .metric-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:15px 16px; }
  .metric-card.warning { border-color:var(--yellow); }
  .metric-card.critical { border-color:var(--red); }
  .metric-head { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:12px; }
  .metric-title { color:var(--muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.7px; }
  .metric-main { font-size:1.35rem; font-weight:800; margin-bottom:4px; }
  .metric-sub { color:var(--muted); font-size:.8rem; min-height:1.2em; }
  .bar-wrap { margin-top:12px; }

  .chart-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:12px; }
  .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:15px 16px; }
  .chart-title { color:var(--muted); font-size:.73rem; text-transform:uppercase; letter-spacing:.65px; margin-bottom:10px; }
  canvas { max-height:155px; }

  @media(max-width:760px){ .check-panel { grid-template-columns:1fr; } .top-right { justify-content:flex-start; } }
</style>
@endsection

@section('content')
<a href="{{ route('dashboard.index') }}" class="back-link">&larr; Semua Laptop</a>

<div class="sub-nav">
  <a href="{{ route('dashboard.show', $device) }}" class="active">Monitor</a>
  <a href="{{ route('dashboard.diagnosis', $device) }}">Diagnosis</a>
  <a href="{{ route('dashboard.history', $device) }}">Riwayat</a>
  <a href="{{ route('dashboard.alerts', $device) }}">Alerts</a>
  <a href="{{ route('dashboard.report', $device) }}">Report</a>
</div>

<div class="device-top">
  <div>
    <div class="device-name">{{ $device->device_name }}</div>
    <div class="device-meta">{{ $device->manufacturer }} {{ $device->model }} � {{ $device->operating_system }} {{ $device->os_version }}</div>
  </div>
  <div class="top-right">
    <span id="online-badge" class="badge {{ $device->isOnline() ? 'badge-normal' : 'badge-offline' }}">{{ $device->isOnline() ? 'ONLINE' : 'OFFLINE' }}</span>
    <span id="last-update" class="last-update">{{ $device->last_seen_at ? 'Terakhir: '.$device->last_seen_at->diffForHumans() : 'Belum ada data' }}</span>
  </div>
</div>

@php
  $score = $reading?->health_score;
  $problemDiagnoses = ($diagnoses ?? collect())->filter(fn ($d) => in_array($d->status, ['WARNING', 'CRITICAL']))->take(3);
  $headline = $score === null ? 'Belum ada data health check' : ($score >= 80 ? 'Laptop dalam kondisi baik' : ($score >= 60 ? 'Laptop perlu perhatian' : 'Laptop dalam kondisi kritis'));
  $summaryCopy = $score === null
      ? 'Jalankan agent agar DeviceCare bisa membaca sensor laptop dan membuat diagnosis awal.'
      : ($problemDiagnoses->isEmpty()
          ? 'Tidak ada masalah besar dari reading terakhir. Pantau suhu, baterai, RAM, dan storage secara berkala.'
          : 'Ada komponen yang perlu dicek. Lihat diagnosis untuk penyebab dan tindakan yang disarankan.');
@endphp

<div class="check-panel">
  <div>
    <div class="check-kicker">Quick Health Check</div>
    <div class="check-title" id="health-headline">{{ $headline }}</div>
    <div class="check-copy" id="health-copy">{{ $summaryCopy }}</div>
    <div class="actions">
      <a class="btn primary" href="{{ route('dashboard.diagnosis', $device) }}">Lihat diagnosis</a>
      <a class="btn" href="{{ route('dashboard.report', $device) }}">Cetak report PDF</a>
    </div>
  </div>
  <div>
    <div class="score-card">
      <div>
        <div class="score-num" id="score-num">{{ $score ?? '-' }}</div>
        <div class="score-label">Health Score / 100</div>
      </div>
      <span id="score-badge" class="badge {{ $score === null ? 'badge-offline' : ($score >= 80 ? 'badge-normal' : ($score >= 60 ? 'badge-warning' : 'badge-critical')) }}">{{ $score === null ? 'NO DATA' : ($score >= 80 ? 'NORMAL' : ($score >= 60 ? 'WARNING' : 'CRITICAL')) }}</span>
    </div>
    <div class="issue-list">
      @forelse($problemDiagnoses as $component => $diag)
        <div class="issue-item {{ strtolower($diag->status) }}">
          <div class="issue-head"><span>{{ strtoupper($component) }}</span><span>{{ $diag->status }}</span></div>
          <div>{{ Str::limit($diag->message ?? 'Perlu pemeriksaan lebih lanjut.', 110) }}</div>
        </div>
      @empty
        <div class="issue-item"><div class="issue-head"><span>Status utama</span><span>OK</span></div><div class="check-copy">Tidak ada warning aktif dari diagnosis terakhir.</div></div>
      @endforelse
    </div>
  </div>
</div>

<div class="metric-grid">
  <div class="metric-card" id="cpu-card">
    <div class="metric-head"><div class="metric-title">CPU</div><span class="badge badge-offline" id="cpu-status">�</span></div>
    <div class="metric-main" id="cpu-usage">�</div>
    <div class="metric-sub" id="cpu-temp">Suhu: �</div>
    <div class="bar-wrap"><div class="bar-fill bar-green" id="cpu-bar" style="width:0%"></div></div>
  </div>
  <div class="metric-card" id="ram-card">
    <div class="metric-head"><div class="metric-title">RAM</div><span class="badge badge-offline" id="ram-status">�</span></div>
    <div class="metric-main" id="ram-usage">�</div>
    <div class="metric-sub" id="ram-avail">Tersedia: �</div>
    <div class="bar-wrap"><div class="bar-fill bar-green" id="ram-bar" style="width:0%"></div></div>
  </div>
  <div class="metric-card" id="disk-card">
    <div class="metric-head"><div class="metric-title">Storage</div><span class="badge badge-offline" id="disk-status">�</span></div>
    <div class="metric-main" id="disk-usage">�</div>
    <div class="metric-sub" id="disk-free">Sisa: �</div>
    <div class="bar-wrap"><div class="bar-fill bar-green" id="disk-bar" style="width:0%"></div></div>
  </div>
  <div class="metric-card" id="batt-card">
    <div class="metric-head"><div class="metric-title">Battery</div><span class="badge badge-offline" id="batt-status">�</span></div>
    <div class="metric-main" id="batt-level">�</div>
    <div class="metric-sub" id="batt-extra">Wear: �</div>
    <div class="bar-wrap"><div class="bar-fill bar-green" id="batt-bar" style="width:0%"></div></div>
  </div>
  <div class="metric-card" id="gpu-card">
    <div class="metric-head"><div class="metric-title">GPU</div><span class="badge badge-offline" id="gpu-status">�</span></div>
    <div class="metric-main" id="gpu-temp">�</div>
    <div class="metric-sub" id="gpu-usage">Usage: �</div>
    <div class="bar-wrap"><div class="bar-fill bar-green" id="gpu-bar" style="width:0%"></div></div>
  </div>
  <div class="metric-card">
    <div class="metric-head"><div class="metric-title">Network</div><span class="badge badge-normal">NORMAL</span></div>
    <div class="metric-main" id="net-dl">�</div>
    <div class="metric-sub" id="net-ul">Upload: �</div>
  </div>
</div>

<div class="chart-grid">
  <div class="chart-card"><div class="chart-title">CPU Usage & Suhu</div><canvas id="chart-cpu"></canvas></div>
  <div class="chart-card"><div class="chart-title">RAM Usage</div><canvas id="chart-ram"></canvas></div>
  <div class="chart-card"><div class="chart-title">GPU Temperature</div><canvas id="chart-gpu"></canvas></div>
  <div class="chart-card"><div class="chart-title">Health Score</div><canvas id="chart-health"></canvas></div>
</div>
@endsection

@section('scripts')
<script>
const DEVICE_ID = {{ $device->id }};
const API = '/api';
const POLL_MS = 1000;
const CHART_MAX = 120;
window._rt_labels = [];
let alertMarkers = [];
const rtData = { cpu_usage:[], cpu_temp:[], ram:[], gpu_temp:[], health:[] };

const $ = id => document.getElementById(id);
const fmt = (v,u='%',d=1) => v !== null && v !== undefined ? Number(v).toFixed(d)+u : 'N/A';
const badgeCls = s => ({NORMAL:'badge-normal',WARNING:'badge-warning',CRITICAL:'badge-critical'}[s] || 'badge-offline');
const barCls = p => p >= 90 ? 'bar-red' : p >= 75 ? 'bar-yellow' : 'bar-green';

function setText(id, value){ const el=$(id); if(el) el.textContent=value; }
function setBadge(id, status){ const el=$(id); if(!el)return; el.textContent=status || '�'; el.className='badge '+badgeCls(status); }
function setCard(id, status){ const el=$(id); if(!el)return; el.className='metric-card '+(status || '').toLowerCase(); }
function setBar(id, pct){ const el=$(id); if(!el)return; const n=Math.max(0, Math.min(100, Number(pct || 0))); el.style.width=n+'%'; el.className='bar-fill '+barCls(n); }
function push(key, value){ rtData[key].push(value ?? null); if(rtData[key].length > CHART_MAX) rtData[key].shift(); }
function pushLabel(value){ window._rt_labels.push(value); if(window._rt_labels.length > CHART_MAX) window._rt_labels.shift(); }

function showChartFallback(){
  document.querySelectorAll('.chart-card').forEach(card => {
    if(card.querySelector('.chart-fallback')) return;
    const note = document.createElement('div');
    note.className = 'chart-fallback';
    note.style.color = '#8892a4';
    note.style.fontSize = '.8rem';
    note.style.padding = '18px 0 8px';
    note.textContent = 'Grafik akan tampil setelah library chart berhasil dimuat. Data real-time tetap diperbarui.';
    card.appendChild(note);
  });
}
const alertMarkerPlugin = {
  id: 'alertMarkers',
  afterDraw(chart){
    if(!alertMarkers.length || !chart.data.labels?.length) return;
    const area = chart.chartArea;
    const xScale = chart.scales.x;
    if(!area || !xScale) return;
    const ctx = chart.ctx;
    alertMarkers.slice(0, 12).forEach(marker => {
      const idx = nearestLabelIndex(marker.created_at);
      if(idx < 0) return;
      const x = xScale.getPixelForValue(idx);
      ctx.save();
      ctx.strokeStyle = marker.severity === 'CRITICAL' ? '#ef4444' : '#f59e0b';
      ctx.globalAlpha = .85;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x, area.top);
      ctx.lineTo(x, area.bottom);
      ctx.stroke();
      ctx.restore();
    });
  }
};
if(typeof Chart !== 'undefined') Chart.register(alertMarkerPlugin);
function nearestLabelIndex(iso){
  const target = new Date(iso).getTime();
  if(!Number.isFinite(target) || !window._rt_labels.length) return -1;
  let best = -1, bestDiff = Infinity;
  window._rt_labels.forEach((label, index) => {
    const diff = Math.abs(new Date(label).getTime() - target);
    if(diff < bestDiff){ bestDiff = diff; best = index; }
  });
  return bestDiff <= 10 * 60 * 1000 ? best : -1;
}
function makeChart(id, datasets, yMax=100){
  const canvas = $(id);
  if(typeof Chart === 'undefined' || !canvas) return null;
  return new Chart(canvas.getContext('2d'), {
    type:'line',
    data:{labels:[], datasets},
    options:{responsive:true, maintainAspectRatio:true, animation:{duration:350}, plugins:{legend:{labels:{color:'#8892a4',boxWidth:10,font:{size:10}}}}, scales:{x:{ticks:{color:'#8892a4',font:{size:9},maxTicksLimit:5},grid:{color:'#2d3148'}}, y:{min:0,max:yMax,ticks:{color:'#8892a4',font:{size:9}},grid:{color:'#2d3148'}}}}
  });
}
const charts = {
  cpu: makeChart('chart-cpu', [{label:'Usage %',data:[],borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.1)',fill:true,tension:.35,pointRadius:0},{label:'Temp C',data:[],borderColor:'#ef4444',tension:.35,pointRadius:0}]),
  ram: makeChart('chart-ram', [{label:'RAM %',data:[],borderColor:'#a855f7',backgroundColor:'rgba(168,85,247,.1)',fill:true,tension:.35,pointRadius:0}]),
  gpu: makeChart('chart-gpu', [{label:'Temp C',data:[],borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.1)',fill:true,tension:.35,pointRadius:0}], 110),
  health: makeChart('chart-health', [{label:'Score',data:[],borderColor:'#22c55e',backgroundColor:'rgba(34,197,94,.1)',fill:true,tension:.35,pointRadius:0}]),
};
if(!Object.values(charts).some(Boolean)) showChartFallback();
function updateCharts(){
  const activeCharts = Object.values(charts).filter(Boolean);
  if(!activeCharts.length) return;
  for(const chart of activeCharts) chart.data.labels = window._rt_labels;
  if(charts.cpu){ charts.cpu.data.datasets[0].data = [...rtData.cpu_usage]; charts.cpu.data.datasets[1].data = [...rtData.cpu_temp]; }
  if(charts.ram) charts.ram.data.datasets[0].data = [...rtData.ram];
  if(charts.gpu) charts.gpu.data.datasets[0].data = [...rtData.gpu_temp];
  if(charts.health) charts.health.data.datasets[0].data = [...rtData.health];
  for(const chart of activeCharts) chart.update();
}
async function fetchJson(url, timeoutMs=4000){
  const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
  const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
  try { const r = await fetch(url, controller ? {signal:controller.signal} : {}); return await r.json(); }
  finally { if(timer) clearTimeout(timer); }
}
function updateScore(score){
  setText('score-num', score ?? '-');
  const badge=$('score-badge');
  if(!badge)return;
  const status = score === null || score === undefined ? 'NO DATA' : score >= 80 ? 'NORMAL' : score >= 60 ? 'WARNING' : 'CRITICAL';
  badge.textContent = status;
  badge.className = 'badge ' + (status === 'NO DATA' ? 'badge-offline' : badgeCls(status));
  setText('health-headline', score === null || score === undefined ? 'Belum ada data health check' : score >= 80 ? 'Laptop dalam kondisi baik' : score >= 60 ? 'Laptop perlu perhatian' : 'Laptop dalam kondisi kritis');
}
function applyReading(data){
  const ts = data.recorded_at || new Date().toISOString();
  if(window._rt_labels[window._rt_labels.length-1] !== ts){
    pushLabel(ts); push('cpu_usage', data.cpu_usage); push('cpu_temp', data.cpu_temperature); push('ram', data.ram_usage); push('gpu_temp', data.gpu_temperature); push('health', data.health_score);
  }
  updateCharts();
  setText('last-update', 'Update: '+new Date(ts).toLocaleTimeString('id'));
  setText('online-badge', 'ONLINE'); $('online-badge').className='badge badge-normal';
  const st = data.statuses || {};
  setBadge('cpu-status', st.cpu); setCard('cpu-card', st.cpu); setText('cpu-usage', fmt(data.cpu_usage)); setText('cpu-temp', 'Suhu: '+(data.cpu_temperature!=null?fmt(data.cpu_temperature,' C'):'N/A')); setBar('cpu-bar', data.cpu_usage);
  setBadge('ram-status', st.ram); setCard('ram-card', st.ram); setText('ram-usage', fmt(data.ram_usage)); setText('ram-avail', 'Tersedia: '+(data.ram_available_gb!=null?data.ram_available_gb+' GB':'N/A')); setBar('ram-bar', data.ram_usage);
  setBadge('disk-status', st.disk); setCard('disk-card', st.disk); setText('disk-usage', fmt(data.disk_usage)); setText('disk-free', 'Sisa: '+(data.disk_free_gb!=null?data.disk_free_gb+' GB':'N/A')); setBar('disk-bar', data.disk_usage);
  setBadge('batt-status', st.battery); setCard('batt-card', st.battery); setText('batt-level', fmt(data.battery_percentage)); setText('batt-extra', 'Wear: '+(data.battery_wear_level!=null?Number(data.battery_wear_level).toFixed(1)+'%':'N/A')); setBar('batt-bar', data.battery_percentage);
  setBadge('gpu-status', st.gpu); setCard('gpu-card', st.gpu); setText('gpu-temp', data.gpu_temperature!=null?fmt(data.gpu_temperature,' C'):'N/A'); setText('gpu-usage', 'Usage: '+fmt(data.gpu_usage)); setBar('gpu-bar', data.gpu_usage);
  setText('net-dl', fmt(data.network_download, ' KB/s')); setText('net-ul', 'Upload: '+fmt(data.network_upload, ' KB/s'));
  updateScore(data.health_score);
}
async function loadAlertMarkers(){
  try{
    const {data} = await fetchJson(`${API}/devices/${DEVICE_ID}/alerts?limit=20`, 5000);
    alertMarkers = Array.isArray(data) ? data : [];
    updateCharts();
  }catch(e){}
}
async function preloadHistory(){
  try{
    const {data} = await fetchJson(`${API}/devices/${DEVICE_ID}/readings/history?minutes=5`, 5000);
    (data || []).forEach(d => { pushLabel(d.t); push('cpu_usage', d.cpu_usage); push('cpu_temp', d.cpu_temperature); push('ram', d.ram_usage); push('gpu_temp', d.gpu_temperature); push('health', d.health_score); });
    updateCharts();
  }catch(e){}
}
let failures = 0;
async function poll(){
  try{
    const {data} = await fetchJson(`${API}/devices/${DEVICE_ID}/readings/latest`);
    if(data) applyReading(data);
    failures = 0;
  }catch(e){
    if(++failures >= 3){ setText('online-badge','OFFLINE'); $('online-badge').className='badge badge-offline'; setText('last-update','Koneksi terputus'); }
  }
}
preloadHistory().then(() => { poll(); setInterval(poll, POLL_MS); });
loadAlertMarkers();
setInterval(loadAlertMarkers, 30000);
</script>
@endsection

