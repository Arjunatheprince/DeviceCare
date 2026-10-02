<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DiagnosticResult;
use App\Services\BatteryIntelligenceService;
use App\Services\HealthScoreBreakdownService;
use App\Services\MaintenanceHealthService;
use App\Services\PredictiveMaintenanceService;
use App\Services\ProcessInsightService;
use App\Services\RootCauseAnalysisService;
use App\Services\StorageAnalysisService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BatteryIntelligenceService $batteryIntelligenceService,
        private readonly HealthScoreBreakdownService $healthScoreService,
        private readonly MaintenanceHealthService $maintenanceHealthService,
        private readonly PredictiveMaintenanceService $predictiveService,
        private readonly ProcessInsightService $processInsightService,
        private readonly RootCauseAnalysisService $rootCauseService,
        private readonly StorageAnalysisService $storageAnalysisService,
    ) {}

    public function index(): View
    {
        $devices = Device::where('is_active', true)
            ->orderByDesc('last_seen_at')
            ->get();

        return view('dashboard.index', compact('devices'));
    }

    public function show(Device $device): View
    {
        $reading = $device->latestReading();

        $diagnoses = DiagnosticResult::where('device_id', $device->id)
            ->where('component', '!=', 'overall')
            ->orderByDesc('diagnosed_at')
            ->get()
            ->groupBy('component')
            ->map(fn ($group) => $group->first());

        $activeAlerts = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard.show', compact('device', 'reading', 'diagnoses', 'activeAlerts'));
    }

    public function diagnosis(Device $device): View
    {
        $reading = $device->latestReading();

        $diagnoses = DiagnosticResult::where('device_id', $device->id)
            ->where('component', '!=', 'overall')
            ->orderByDesc('diagnosed_at')
            ->get()
            ->groupBy('component')
            ->map(fn ($group) => $group->first());

        // Riwayat alert: ambil dari tabel alerts (bukan diagnostic_results)
        // Satu alert per komponen per severity — tidak menumpuk
        $alerts = \App\Models\Alert::where('device_id', $device->id)
            ->whereIn('severity', ['WARNING', 'CRITICAL'])
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('dashboard.diagnosis', compact('device', 'reading', 'diagnoses', 'alerts'));
    }

    public function history(Device $device): View
    {
        return view('dashboard.history', compact('device'));
    }

    /**
     * GET /devices/{device}/alerts
     * Halaman daftar alert aktif + riwayat.
     */
    public function alerts(Device $device): View
    {
        $activeAlerts = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $recentResolved = Alert::where('device_id', $device->id)
            ->where('is_resolved', true)
            ->where('resolved_at', '>=', now()->subHours(24))
            ->orderByDesc('resolved_at')
            ->limit(20)
            ->get();

        $criticalCount = $activeAlerts->where('severity', 'CRITICAL')->count();
        $warningCount  = $activeAlerts->where('severity', 'WARNING')->count();

        return view('dashboard.alerts', compact(
            'device', 'activeAlerts', 'recentResolved', 'criticalCount', 'warningCount'
        ));
    }
    public function report(Device $device): View
    {
        $reading = $device->latestReading();

        $diagnoses = DiagnosticResult::where('device_id', $device->id)
            ->orderByDesc('diagnosed_at')
            ->get()
            ->groupBy('component')
            ->map(fn ($group) => $group->first());

        $activeAlerts = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $history = $device->sensorReadings()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get();

        $averages = [
            'cpu_usage' => round((float) $history->avg('cpu_usage'), 1),
            'cpu_temperature' => round((float) $history->avg('cpu_temperature'), 1),
            'ram_usage' => round((float) $history->avg('ram_usage'), 1),
            'disk_usage' => round((float) $history->avg('disk_usage'), 1),
            'health_score' => round((float) $history->avg('health_score'), 1),
        ];

        $reportIntelligence = [
            'battery' => $this->batteryIntelligenceService->analyze($device, 24),
            'health_score' => $this->healthScoreService->analyze($device),
            'maintenance' => $this->maintenanceHealthService->analyze($device),
            'predictions' => $this->predictiveService->analyze($device, 14),
            'process' => $this->processInsightService->analyze($device, 15),
            'root_cause' => $this->rootCauseService->analyze($device),
            'storage' => $this->storageAnalysisService->analyze($device, 14),
        ];

        return view('dashboard.report', compact('device', 'reading', 'diagnoses', 'activeAlerts', 'history', 'averages', 'reportIntelligence'));
    }
}
