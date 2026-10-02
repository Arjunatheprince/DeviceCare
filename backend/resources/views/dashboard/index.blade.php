@extends('layouts.app')

@section('title', 'Laptop Health Check')

@section('styles')
<style>
  .landing-simple {
    min-height: calc(100vh - 112px);
    display: grid;
    align-items: center;
  }
  .landing-wrap {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(280px, .75fr);
    gap: 48px;
    align-items: center;
    padding: 62px 0;
  }
  .landing-copy-wrap { max-width: 680px; }
  .landing-kicker {
    color: #93c5fd;
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 800;
    margin-bottom: 12px;
  }
  .landing-title {
    font-size: clamp(2.35rem, 6vw, 4.55rem);
    line-height: 1.04;
    font-weight: 900;
    margin-bottom: 16px;
  }
  .landing-copy {
    color: var(--muted);
    font-size: 1rem;
    line-height: 1.75;
    text-align: justify;
    text-align-last: left;
  }
  .landing-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 26px;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 16px;
    font-size: .88rem;
    font-weight: 750;
    background: var(--surface2);
    color: var(--text);
  }
  .btn.primary { background: var(--blue); border-color: var(--blue); color: #fff; }
  .btn:hover { border-color: var(--blue); }

  .landing-art {
    position: relative;
    min-height: 360px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background:
      linear-gradient(90deg, rgba(148,163,184,.08) 1px, transparent 1px),
      linear-gradient(0deg, rgba(148,163,184,.08) 1px, transparent 1px),
      rgba(26,29,39,.42);
    background-size: 34px 34px;
    overflow: hidden;
  }
  .landing-art:before,
  .landing-art:after {
    content: "";
    position: absolute;
    border: 1px solid rgba(59,130,246,.48);
    border-radius: 10px;
    transform: rotate(-8deg);
  }
  .landing-art:before {
    width: 190px;
    height: 116px;
    top: 64px;
    right: 46px;
    background: rgba(59,130,246,.08);
  }
  .landing-art:after {
    width: 138px;
    height: 138px;
    left: 42px;
    bottom: 46px;
    border-color: rgba(34,197,94,.42);
    background: rgba(34,197,94,.06);
    transform: rotate(12deg);
  }
  .art-line {
    position: absolute;
    left: 54px;
    right: 42px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(148,163,184,.55), transparent);
  }
  .art-line.one { top: 118px; }
  .art-line.two { top: 194px; }
  .art-line.three { top: 270px; }
  .art-chip {
    position: absolute;
    display: block;
    height: 9px;
    border-radius: 999px;
    background: rgba(148,163,184,.28);
  }
  .art-chip.blue { width: 96px; left: 58px; top: 86px; background: rgba(59,130,246,.6); }
  .art-chip.green { width: 132px; right: 58px; top: 172px; background: rgba(34,197,94,.55); }
  .art-chip.gray { width: 78px; left: 104px; bottom: 98px; }
  .art-corner {
    position: absolute;
    width: 72px;
    height: 72px;
    border-top: 2px solid rgba(245,158,11,.5);
    border-right: 2px solid rgba(245,158,11,.5);
    right: 34px;
    bottom: 34px;
  }

  @media(max-width: 860px){
    .landing-wrap { grid-template-columns: 1fr; gap: 28px; padding: 36px 0; }
    .landing-art { min-height: 250px; }
  }
  @media(max-width: 560px){
    .landing-title { font-size: 2.3rem; }
    .landing-copy { text-align: left; }
    .landing-art { min-height: 220px; }
  }
</style>
@endsection

@section('content')
@php $firstDevice = $devices->first(); @endphp

<section class="landing-simple">
  <div class="landing-wrap">
    <div class="landing-copy-wrap">
      <div class="landing-kicker">DeviceCare</div>
      <h1 class="landing-title">Cek kesehatan laptop dengan cepat</h1>
      <p class="landing-copy">
        Pantau suhu, RAM, baterai, storage, jaringan, dan health score laptop dalam satu dashboard real-time yang mudah dibaca.
      </p>

      <div class="landing-actions">
        @if($firstDevice)
          <a class="btn primary" href="{{ route('dashboard.show', $firstDevice) }}">Buka monitor laptop</a>
        @else
          <a class="btn primary" href="{{ route('dashboard.index') }}">Menunggu data laptop</a>
        @endif
      </div>
    </div>

    <div class="landing-art" aria-hidden="true">
      <span class="art-line one"></span>
      <span class="art-line two"></span>
      <span class="art-line three"></span>
      <span class="art-chip blue"></span>
      <span class="art-chip green"></span>
      <span class="art-chip gray"></span>
      <span class="art-corner"></span>
    </div>
  </div>
</section>
@endsection
