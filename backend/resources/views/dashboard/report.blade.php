@extends('layouts.app')

@section('title', 'Health Report - '.$device->device_name)

@section('styles')
<style>
  .report-actions { display:flex; gap:10px; justify-content:space-between; align-items:center; margin-bottom:18px; }
  .btn { border:1px solid var(--border); background:var(--surface2); color:var(--text); padding:8px 13px; border-radius:8px; font-size:.84rem; cursor:pointer; }
  .btn:hover { border-color:var(--blue); }
  .report-sheet { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:26px; }
  .report-head { display:flex; justify-content:space-between; gap:20px; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:22px; }
  .report-title { font-size:1.35rem; font-weight:750; margin-bottom:6px; }
  .report-sub { color:var(--muted); font-size:.86rem; line-height:1.5; }
  .score-box { text-align:right; min-width:150px; }
  .score-big { font-size:2.4rem; font-weight:800; line-height:1; }
  .score-caption { color:var(--muted); font-size:.78rem; margin-top:4px; }
  .report-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin-bottom:22px; }
  .metric { border:1px solid var(--border); border-radius:10px; padding:13px 14px; background:rgba(255,255,255,.015); }
  .metric-label { color:var(--muted); font-size:.72rem; text-transform:uppercase; letter-spacing:.6px; margin-bottom:6px; }
  .metric-value { font-size:1.1rem; font-weight:700; }
  .section { margin-top:22px; }
  .section h2 { font-size:.92rem; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:10px; }
  .diag-list { display:flex; flex-direction:column; gap:10px; }
  .diag-row { border:1px solid var(--border); border-radius:10px; padding:12px 14px; }
  .diag-row.warning { border-color:var(--yellow); background:rgba(245,158,11,.06); }
  .diag-row.critical { border-color:var(--red); background:rgba(239,68,68,.06); }
  .diag-top { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:6px; }
  .diag-name { font-weight:700; text-transform:uppercase; font-size:.82rem; }
  .diag-msg { color:var(--text); font-size:.86rem; line-height:1.55; }
  .suggestions { margin-top:7px; color:var(--muted); font-size:.82rem; line-height:1.55; }
  .suggestions ul { margin-left:18px; }
  .privacy-note { color:var(--muted); font-size:.8rem; line-height:1.55; border-top:1px solid var(--border); padding-top:14px; margin-top:24px; }
  @media print {
    nav, .report-actions { display:none !important; }
    body { background:#fff; color:#111; }
    .container { max-width:none; padding:0; }
    .report-sheet { border:none; background:#fff; color:#111; padding:0; }
    .metric, .diag-row { border-color:#ddd; background:#fff !important; }
    .report-sub, .metric-label, .section h2, .suggestions, .privacy-note, .score-caption { color:#555; }
    .badge { border:1px solid #ddd; color:#111 !important; background:#fff !important; }
  }
</style>
@endsection

@section('content')
<div class="report-actions">
  <a href="{{ route('dashboard.show', $device) }}" class="btn">Kembali ke monitor</a>
  <button class="btn" onclick="window.print()">Cetak / Save PDF</button>
</div>

@php
  $score = $reading?->health_score;
  $scoreLabel = $score === null ? 'Belum ada data' : ($score >= 80 ? 'Kondisi baik' : ($score >= 60 ? 'Perlu perhatian' : 'Kondisi kritis'));
  $components = ['cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM', 'disk' => 'Storage', 'smart' => 'Disk Health', 'battery' => 'Battery', 'overall' => 'Overall'];
  $orderedDiagnoses = collect($components)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'diag' => $diagnoses->get($key)])->filter(fn ($item) => $item['diag']);
@endphp

<div class="report-sheet">
  <div class="report-head">
    <div>
      <div class="report-title">DeviceCare Laptop Health Report</div>
      <div class="report-sub">
        {{ $device->device_name }} - {{ $device->manufacturer }} {{ $device->model }}<br>
        {{ $device->operating_system }} {{ $device->os_version }}<br>
        Dibuat: {{ now()->format('d M Y H:i') }}
      </div>
    </div>
    <div class="score-box">
      <div class="score-big">{{ $score ?? '-' }}</div>
      <div class="score-caption">Health Score / 100</div>
      <div style="margin-top:8px"><span class="badge {{ $score === null ? 'badge-offline' : ($score >= 80 ? 'badge-normal' : ($score >= 60 ? 'badge-warning' : 'badge-critical')) }}">{{ $scoreLabel }}</span></div>
    </div>
  </div>

  <div class="report-grid">
    <div class="metric"><div class="metric-label">CPU saat ini</div><div class="metric-value">{{ $reading?->cpu_usage !== null ? number_format($reading->cpu_usage,1).'%' : '-' }}</div></div>
    <div class="metric"><div class="metric-label">Suhu CPU</div><div class="metric-value">{{ $reading?->cpu_temperature !== null ? number_format($reading->cpu_temperature,1).' C' : 'N/A' }}</div></div>
    <div class="metric"><div class="metric-label">RAM</div><div class="metric-value">{{ $reading?->ram_usage !== null ? number_format($reading->ram_usage,1).'%' : '-' }}</div></div>
    <div class="metric"><div class="metric-label">Storage</div><div class="metric-value">{{ $reading?->disk_usage !== null ? number_format($reading->disk_usage,1).'%' : '-' }}</div></div>
    <div class="metric"><div class="metric-label">Battery Wear</div><div class="metric-value">{{ $reading?->battery_wear_level !== null ? number_format($reading->battery_wear_level,1).'%' : 'N/A' }}</div></div>
    <div class="metric"><div class="metric-label">SMART Disk</div><div class="metric-value">{{ $reading?->smart_health_status ?? 'N/A' }}</div></div>
  </div>

  <div class="section">
    <h2>Ringkasan 24 Jam</h2>
    <div class="report-grid">
      <div class="metric"><div class="metric-label">Rata-rata CPU</div><div class="metric-value">{{ $history->count() ? $averages['cpu_usage'].'%' : '-' }}</div></div>
      <div class="metric"><div class="metric-label">Rata-rata suhu CPU</div><div class="metric-value">{{ $history->count() && $averages['cpu_temperature'] > 0 ? $averages['cpu_temperature'].' C' : 'N/A' }}</div></div>
      <div class="metric"><div class="metric-label">Rata-rata RAM</div><div class="metric-value">{{ $history->count() ? $averages['ram_usage'].'%' : '-' }}</div></div>
      <div class="metric"><div class="metric-label">Jumlah reading</div><div class="metric-value">{{ $history->count() }}</div></div>
    </div>
  </div>

  <div class="section">
    <h2>Diagnosis dan Rekomendasi</h2>
    @if($orderedDiagnoses->isEmpty())
      <div class="diag-row"><div class="diag-msg">Belum ada diagnosis. Jalankan agent selama beberapa menit lalu buka laporan ini lagi.</div></div>
    @else
      <div class="diag-list">
        @foreach($orderedDiagnoses as $item)
          @php $diag = $item['diag']; @endphp
          <div class="diag-row {{ strtolower($diag->status) }}">
            <div class="diag-top">
              <div class="diag-name">{{ $item['label'] }}</div>
              <span class="badge {{ $diag->status === 'NORMAL' ? 'badge-normal' : ($diag->status === 'WARNING' ? 'badge-warning' : 'badge-critical') }}">{{ $diag->status }}</span>
            </div>
            <div class="diag-msg">{{ $diag->message ?? 'Tidak ada pesan diagnosis.' }}</div>
            @if(!empty($diag->suggestions))
              <div class="suggestions">
                <strong>Saran:</strong>
                <ul>
                  @foreach($diag->suggestions as $suggestion)
                    <li>{{ $suggestion }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="section">
    <h2>Alert Aktif</h2>
    @if($activeAlerts->isEmpty())
      <div class="diag-row"><div class="diag-msg">Tidak ada alert aktif.</div></div>
    @else
      <div class="diag-list">
        @foreach($activeAlerts as $alert)
          <div class="diag-row {{ strtolower($alert->severity) }}">
            <div class="diag-top">
              <div class="diag-name">{{ strtoupper($alert->component) }}</div>
              <span class="badge {{ $alert->severity === 'CRITICAL' ? 'badge-critical' : 'badge-warning' }}">{{ $alert->severity }}</span>
            </div>
            <div class="diag-msg">{{ $alert->message }}</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>


  <div class="section">
    <h2>Executive Intelligence</h2>
    @php
      $intelGroups = [
        'Root Cause' => $reportIntelligence['root_cause']['causes'] ?? [],
        'Prediksi Maintenance' => $reportIntelligence['predictions'] ?? [],
        'Storage Analyzer' => $reportIntelligence['storage']['insights'] ?? [],
        'Battery Intelligence' => $reportIntelligence['battery']['insights'] ?? [],
        'Process Intelligence' => $reportIntelligence['process']['insights'] ?? [],
        'Maintenance Health' => $reportIntelligence['maintenance']['insights'] ?? [],
        'Health Score Factors' => $reportIntelligence['health_score']['top_penalties'] ?? [],
      ];
      $hasIntel = collect($intelGroups)->filter(fn ($items) => !empty($items))->isNotEmpty();
    @endphp
    <div class="diag-list">
      @foreach($intelGroups as $groupName => $items)
        @if(!empty($items))
          @foreach(array_slice($items, 0, 3) as $item)
            @php
              $severity = $item['severity'] ?? $item['status'] ?? 'WARNING';
              $message = $item['label'] ?? $item['message'] ?? $item['reason'] ?? 'Insight tersedia.';
              $suggestion = $item['suggestion'] ?? null;
            @endphp
            <div class="diag-row {{ strtolower($severity) }}">
              <div class="diag-top">
                <div class="diag-name">{{ $groupName }}</div>
                <span class="badge {{ $severity === 'CRITICAL' ? 'badge-critical' : ($severity === 'NORMAL' ? 'badge-normal' : 'badge-warning') }}">{{ $severity }}</span>
              </div>
              <div class="diag-msg">{{ $message }}</div>
              @if($suggestion)
                <div class="suggestions"><strong>Saran:</strong> {{ $suggestion }}</div>
              @endif
            </div>
          @endforeach
        @endif
      @endforeach
      @unless($hasIntel)
        <div class="diag-row"><div class="diag-msg">Tidak ada insight intelligence besar pada data terbaru.</div></div>
      @endunless
    </div>
  </div>
  <div class="privacy-note">
    Catatan privasi: laporan ini berisi data hardware dan performa, bukan isi file pribadi. Gunakan laporan ini sebagai bantuan awal, bukan pengganti diagnosis teknisi bila ada indikasi kerusakan fisik serius.
  </div>
</div>
@endsection