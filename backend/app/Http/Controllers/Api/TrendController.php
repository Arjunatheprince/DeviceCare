<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AnomalyDetectionService;
use App\Services\BaselineService;
use App\Services\BatteryIntelligenceService;
use App\Services\HealthScoreBreakdownService;
use App\Services\MaintenanceHealthService;
use App\Services\PredictiveMaintenanceService;
use App\Services\ProcessInsightService;
use App\Services\RootCauseAnalysisService;
use App\Services\StorageAnalysisService;
use App\Services\TrendAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TrendController
 *
 * Endpoints untuk Milestone 4 — Intelligent Monitoring:
 *
 *   GET  /api/devices/{device}/trends          → ringkasan trend semua metrik
 *   GET  /api/devices/{device}/trends/consistent → kenaikan/penurunan konsisten
 *   GET  /api/devices/{device}/anomalies        → anomali berdasarkan baseline
 *   GET  /api/devices/{device}/baseline         → statistik baseline device
 *   POST /api/devices/{device}/baseline/refresh → force-refresh baseline
 *   GET  /api/devices/{device}/intelligence     → gabungan trend + anomali + baseline (one-shot)
 */
class TrendController extends Controller
{
    public function __construct(
        private readonly TrendAnalysisService   $trendService,
        private readonly AnomalyDetectionService $anomalyService,
        private readonly BaselineService         $baselineService,
        private readonly BatteryIntelligenceService $batteryIntelligenceService,
        private readonly MaintenanceHealthService $maintenanceHealthService,
        private readonly PredictiveMaintenanceService $predictiveService,
        private readonly ProcessInsightService $processInsightService,
        private readonly RootCauseAnalysisService $rootCauseService,
        private readonly StorageAnalysisService $storageAnalysisService,
        private readonly HealthScoreBreakdownService $healthScoreService,
    ) {}

    /**
     * GET /api/devices/{device}/trends?days=7
     *
     * Ringkasan trend semua metrik: perbandingan 7 hari terakhir vs 7 hari sebelumnya.
     */
    public function trends(Request $request, Device $device): JsonResponse
    {
        $days = min((int) $request->query('days', 7), 30);

        $trends = $this->trendService->analyzeDevice($device, $days);

        // Hitung overall trend summary
        $warningCount  = collect($trends)->where('severity', 'warning')->count();
        $criticalCount = collect($trends)->where('severity', 'critical')->count();

        $overallSeverity = match (true) {
            $criticalCount > 0 => 'critical',
            $warningCount  > 0 => 'warning',
            default            => 'good',
        };

        return response()->json([
            'device_id'       => $device->id,
            'device_name'     => $device->device_name,
            'analysis_days'   => $days,
            'overall_severity'=> $overallSeverity,
            'warning_count'   => $warningCount,
            'critical_count'  => $criticalCount,
            'metrics'         => $trends,
        ]);
    }

    /**
     * GET /api/devices/{device}/trends/consistent?days=5
     *
     * Deteksi kenaikan/penurunan konsisten (misal suhu naik terus 5 hari).
     */
    public function consistent(Request $request, Device $device): JsonResponse
    {
        $days = min((int) $request->query('days', 5), 14);

        $alerts = $this->trendService->detectConsistentTrend($device, $days);

        return response()->json([
            'device_id'   => $device->id,
            'device_name' => $device->device_name,
            'window_days' => $days,
            'found'       => count($alerts),
            'alerts'      => $alerts,
            'summary'     => count($alerts) === 0
                ? 'Tidak ada pola kenaikan/penurunan konsisten terdeteksi.'
                : count($alerts) . ' metrik menunjukkan pola yang perlu diperhatikan.',
        ]);
    }

    /**
     * GET /api/devices/{device}/anomalies
     *
     * Deteksi anomali pada reading terbaru berdasarkan baseline historis device.
     * Menggunakan Z-score: nilai di luar distribusi normal perangkat ini sendiri.
     */
    public function anomalies(Request $request, Device $device): JsonResponse
    {
        $latest = $device->latestReading();

        if (!$latest) {
            return response()->json([
                'device_id'    => $device->id,
                'anomalies'    => [],
                'summary'      => 'Belum ada reading untuk dianalisis.',
                'baseline_ready' => false,
            ]);
        }

        $baseline = $this->baselineService->getBaseline($device);
        $device->refresh(); // ambil baseline_ready yang mungkin baru di-update

        if (empty($baseline)) {
            return response()->json([
                'device_id'      => $device->id,
                'anomalies'      => [],
                'baseline_ready' => false,
                'summary'        => 'Baseline belum tersedia. Dibutuhkan minimal 3 hari data untuk mulai deteksi anomali.',
            ]);
        }

        $anomalies = $this->anomalyService->detect($device, $latest, $baseline);

        return response()->json([
            'device_id'        => $device->id,
            'device_name'      => $device->device_name,
            'reading_id'       => $latest->id,
            'recorded_at'      => $latest->recorded_at?->toIso8601String(),
            'baseline_ready'   => (bool) $device->baseline_ready,
            'anomaly_count'    => count($anomalies),
            'anomalies'        => $anomalies,
            'summary'          => $this->anomalySummary($anomalies, $device->baseline_ready),
        ]);
    }

    /**
     * GET /api/devices/{device}/baseline
     *
     * Tampilkan statistik baseline device (mean, stddev, sample_count per metrik).
     */
    public function baseline(Device $device): JsonResponse
    {
        $summary = $this->baselineService->getSummary($device);

        return response()->json([
            'device_id'   => $device->id,
            'device_name' => $device->device_name,
            'baseline'    => $summary,
        ]);
    }

    /**
     * POST /api/devices/{device}/baseline/refresh
     *
     * Force-refresh baseline untuk device ini.
     * Berguna setelah hardware upgrade atau perubahan kondisi lingkungan permanen.
     */
    public function refreshBaseline(Device $device): JsonResponse
    {
        $ok = $this->baselineService->calculate($device);
        $device->refresh();
        $summary = $this->baselineService->getSummary($device);

        return response()->json([
            'success'     => $ok,
            'device_id'   => $device->id,
            'device_name' => $device->device_name,
            'baseline'    => $summary,
            'message'     => $ok
                ? 'Baseline berhasil diperbarui.'
                : 'Tidak ada data yang cukup untuk menghitung baseline.',
        ], $ok ? 200 : 422);
    }

    /**
     * GET /api/devices/{device}/storage-analysis?days=14
     */
    public function storageAnalysis(Request $request, Device $device): JsonResponse
    {
        $days = min(max((int) $request->query('days', 14), 3), 60);
        return response()->json(['device_id' => $device->id, 'device_name' => $device->device_name, 'analysis' => $this->storageAnalysisService->analyze($device, $days)]);
    }

    /**
     * GET /api/devices/{device}/maintenance-health
     */
    public function maintenanceHealth(Device $device): JsonResponse
    {
        return response()->json(['device_id' => $device->id, 'device_name' => $device->device_name, 'analysis' => $this->maintenanceHealthService->analyze($device)]);
    }

    /**
     * GET /api/devices/{device}/battery-intelligence?hours=24
     *
     * Analisis kebiasaan charging, drain rate, dan battery wear.
     */
    public function batteryIntelligence(Request $request, Device $device): JsonResponse
    {
        $hours = min(max((int) $request->query('hours', 24), 3), 168);

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'analysis' => $this->batteryIntelligenceService->analyze($device, $hours),
        ]);
    }

    /**
     * GET /api/devices/{device}/health-score
     *
     * Breakdown bobot dan faktor penurun health score terbaru.
     */
    public function healthScore(Device $device): JsonResponse
    {
        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'breakdown' => $this->healthScoreService->analyze($device),
        ]);
    }

    /**
     * GET /api/devices/{device}/root-cause
     *
     * Analisis akar masalah dari reading terbaru dan top process snapshot.
     */
    public function rootCause(Device $device): JsonResponse
    {
        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'analysis' => $this->rootCauseService->analyze($device),
        ]);
    }

    /**
     * GET /api/devices/{device}/predictions?days=14
     *
     * Prediksi maintenance awal: storage menuju penuh, battery wear, dan thermal drift.
     */
    public function predictions(Request $request, Device $device): JsonResponse
    {
        $days = min((int) $request->query('days', 14), 60);
        $predictions = $this->predictiveService->analyze($device, $days);

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'analysis_days' => $days,
            'prediction_count' => count($predictions),
            'warning_count' => collect($predictions)->whereIn('severity', ['WARNING', 'CRITICAL'])->count(),
            'predictions' => $predictions,
        ]);
    }

    /**
     * GET /api/devices/{device}/process-insights?minutes=15
     *
     * Deteksi proses CPU/RAM bermasalah dari snapshot aplikasi terbaru.
     */
    public function processInsights(Request $request, Device $device): JsonResponse
    {
        $minutes = min(max((int) $request->query('minutes', 15), 5), 120);

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'analysis' => $this->processInsightService->analyze($device, $minutes),
        ]);
    }

    /**
     * GET /api/devices/{device}/intelligence
     *
     * One-shot endpoint: menggabungkan trend + anomali + baseline + consistent trends.
     * Dirancang untuk dashboard — satu request untuk semua data M4.
     */
    public function intelligence(Request $request, Device $device): JsonResponse
    {
        $days = min((int) $request->query('days', 7), 30);

        // Jalankan semua analisis
        $trends     = $this->trendService->analyzeDevice($device, $days);
        $consistent = $this->trendService->detectConsistentTrend($device, min($days, 7));
        $baseline   = $this->baselineService->getBaseline($device);
        $predictions = $this->predictiveService->analyze($device, max($days, 14));
        $rootCause = $this->rootCauseService->analyze($device);
        $healthScore = $this->healthScoreService->analyze($device);
        $processInsights = $this->processInsightService->analyze($device, 15);
        $batteryIntelligence = $this->batteryIntelligenceService->analyze($device, 24);
        $storageAnalysis = $this->storageAnalysisService->analyze($device, max($days, 14));
        $maintenanceHealth = $this->maintenanceHealthService->analyze($device);
        $device->refresh();

        $anomalies = [];
        $latest    = $device->latestReading();
        if ($latest && !empty($baseline)) {
            $anomalies = $this->anomalyService->detect($device, $latest, $baseline);
        }

        // Severity agregat
        $criticalAnomaly   = collect($anomalies)->where('severity', 'CRITICAL')->count();
        $warningAnomaly    = collect($anomalies)->where('severity', 'WARNING')->count();
        $criticalTrend     = collect($trends)->where('severity', 'critical')->count();
        $warningTrend      = collect($trends)->where('severity', 'warning')->count();
        $criticalPrediction = collect($predictions)->where('severity', 'CRITICAL')->count();
        $warningPrediction  = collect($predictions)->where('severity', 'WARNING')->count();
        $rootCauseCritical  = collect($rootCause['causes'] ?? [])->where('severity', 'CRITICAL')->count();
        $rootCauseWarning   = collect($rootCause['causes'] ?? [])->where('severity', 'WARNING')->count();
        $processCritical    = collect($processInsights['insights'] ?? [])->where('severity', 'CRITICAL')->count();
        $processWarning     = collect($processInsights['insights'] ?? [])->where('severity', 'WARNING')->count();
        $batteryCritical    = collect($batteryIntelligence['insights'] ?? [])->where('severity', 'CRITICAL')->count();
        $batteryWarning     = collect($batteryIntelligence['insights'] ?? [])->where('severity', 'WARNING')->count();
        $storageCritical    = collect($storageAnalysis['insights'] ?? [])->where('severity', 'CRITICAL')->count();
        $storageWarning     = collect($storageAnalysis['insights'] ?? [])->where('severity', 'WARNING')->count();
        $maintenanceCritical = collect($maintenanceHealth['insights'] ?? [])->where('severity', 'CRITICAL')->count();
        $maintenanceWarning  = collect($maintenanceHealth['insights'] ?? [])->where('severity', 'WARNING')->count();

        $overallSeverity = match (true) {
            ($criticalAnomaly + $criticalTrend + $criticalPrediction + $rootCauseCritical + $processCritical + $batteryCritical + $storageCritical + $maintenanceCritical) > 0 => 'CRITICAL',
            ($warningAnomaly + $warningTrend + $warningPrediction + $rootCauseWarning + $processWarning + $batteryWarning + $storageWarning + $maintenanceWarning) > 0 => 'WARNING',
            default                                  => 'NORMAL',
        };

        // Kumpulkan insights paling penting (maks 5) untuk ditampilkan di dashboard
        $insights = $this->collectTopInsights($anomalies, $consistent, $trends, $predictions, $rootCause, $healthScore, $processInsights, $batteryIntelligence, $storageAnalysis, $maintenanceHealth);

        return response()->json([
            'device_id'        => $device->id,
            'device_name'      => $device->device_name,
            'analysis_days'    => $days,
            'overall_severity' => $overallSeverity,
            'baseline_ready'   => (bool) $device->baseline_ready,
            'baseline_age_h'   => $device->baseline_calculated_at
                ? round($device->baseline_calculated_at->diffInMinutes(now()) / 60, 1)
                : null,

            'summary' => [
                'anomaly_count'    => count($anomalies),
                'consistent_count' => count($consistent),
                'trend_warnings'   => $warningTrend + $criticalTrend,
                'prediction_warnings' => $warningPrediction + $criticalPrediction,
                'root_cause_count' => count($rootCause['causes'] ?? []),
                'score_penalty_count' => count($healthScore['top_penalties'] ?? []),
                'process_insight_count' => count($processInsights['insights'] ?? []),
                'battery_insight_count' => count($batteryIntelligence['insights'] ?? []),
                'storage_insight_count' => count($storageAnalysis['insights'] ?? []),
                'maintenance_insight_count' => count($maintenanceHealth['insights'] ?? []),
            ],

            'insights'   => $insights,
            'anomalies'  => $anomalies,
            'consistent' => $consistent,
            'trends'     => $trends,
            'predictions' => $predictions,
            'root_cause' => $rootCause,
            'health_score_breakdown' => $healthScore,
            'process_insights' => $processInsights,
            'battery_intelligence' => $batteryIntelligence,
            'storage_analysis' => $storageAnalysis,
            'maintenance_health' => $maintenanceHealth,
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function anomalySummary(array $anomalies, bool $baselineReady): string
    {
        if (!$baselineReady) {
            return 'Baseline sedang dibangun — dibutuhkan lebih banyak data historis.';
        }
        if (empty($anomalies)) {
            return 'Tidak ada anomali terdeteksi. Semua metrik berada dalam pola normal perangkat ini.';
        }
        $critical = collect($anomalies)->where('severity', 'CRITICAL')->count();
        $warning  = collect($anomalies)->where('severity', 'WARNING')->count();
        $parts    = [];
        if ($critical > 0) $parts[] = "{$critical} anomali kritis";
        if ($warning  > 0) $parts[] = "{$warning} anomali peringatan";
        return implode(', ', $parts) . ' terdeteksi berdasarkan pola normal perangkat ini.';
    }

    /**
     * Kumpulkan max 5 insight paling penting dari semua analisis.
     */
    private function collectTopInsights(array $anomalies, array $consistent, array $trends, array $predictions = [], array $rootCause = [], array $healthScore = [], array $processInsights = [], array $batteryIntelligence = [], array $storageAnalysis = [], array $maintenanceHealth = []): array
    {
        $insights = [];

        // Storage and maintenance critical insights
        foreach (array_merge($storageAnalysis['insights'] ?? [], $maintenanceHealth['insights'] ?? []) as $opsInsight) {
            if (($opsInsight['severity'] ?? null) === 'CRITICAL') {
                $insights[] = ['type' => 'ops_health', 'severity' => 'CRITICAL', 'text' => $opsInsight['label']];
            }
        }
        // Battery critical insights
        foreach (($batteryIntelligence['insights'] ?? []) as $batteryInsight) {
            if (($batteryInsight['severity'] ?? null) === 'CRITICAL') {
                $insights[] = ['type' => 'battery_intelligence', 'severity' => 'CRITICAL', 'text' => $batteryInsight['label']];
            }
        }
        // Process insights paling berat dulu
        foreach (($processInsights['insights'] ?? []) as $processInsight) {
            if (($processInsight['severity'] ?? null) === 'CRITICAL') {
                $insights[] = ['type' => 'process_insight', 'severity' => 'CRITICAL', 'text' => $processInsight['label']];
            }
        }
        // Root cause tertinggi dulu jika ada
        foreach (($rootCause['causes'] ?? []) as $cause) {
            if (($cause['severity'] ?? null) === 'CRITICAL') {
                $insights[] = ['type' => 'root_cause', 'severity' => 'CRITICAL', 'text' => $cause['message']];
            }
        }
        // CRITICAL anomalies dulu
        foreach ($anomalies as $a) {
            if ($a['severity'] === 'CRITICAL') {
                $insights[] = ['type' => 'anomaly', 'severity' => 'CRITICAL', 'text' => $a['label']];
            }
        }
        // Consistent trends
        foreach ($consistent as $c) {
            $insights[] = ['type' => 'consistent_trend', 'severity' => 'WARNING', 'text' => $c['label']];
        }
        // WARNING anomalies
        foreach ($anomalies as $a) {
            if ($a['severity'] === 'WARNING') {
                $insights[] = ['type' => 'anomaly', 'severity' => 'WARNING', 'text' => $a['label']];
            }
        }
        // Critical/warning trends
        foreach ($trends as $t) {
            if (in_array($t['severity'], ['critical', 'warning'])) {
                $insights[] = ['type' => 'trend', 'severity' => strtoupper($t['severity']), 'text' => $t['label']];
            }
        }
        // Predictive maintenance warnings
        foreach ($predictions as $p) {
            if (in_array($p['severity'], ['CRITICAL', 'WARNING'])) {
                $insights[] = ['type' => 'prediction', 'severity' => $p['severity'], 'text' => $p['label']];
            }
        }
        // WARNING root causes
        foreach (($rootCause['causes'] ?? []) as $cause) {
            if (($cause['severity'] ?? null) === 'WARNING') {
                $insights[] = ['type' => 'root_cause', 'severity' => 'WARNING', 'text' => $cause['message']];
            }
        }
        // WARNING process insights
        foreach (($processInsights['insights'] ?? []) as $processInsight) {
            if (($processInsight['severity'] ?? null) === 'WARNING') {
                $insights[] = ['type' => 'process_insight', 'severity' => 'WARNING', 'text' => $processInsight['label']];
            }
        }
        // Storage and maintenance warning insights
        foreach (array_merge($storageAnalysis['insights'] ?? [], $maintenanceHealth['insights'] ?? []) as $opsInsight) {
            if (($opsInsight['severity'] ?? null) === 'WARNING') {
                $insights[] = ['type' => 'ops_health', 'severity' => 'WARNING', 'text' => $opsInsight['label']];
            }
        }
        // Battery warning insights
        foreach (($batteryIntelligence['insights'] ?? []) as $batteryInsight) {
            if (($batteryInsight['severity'] ?? null) === 'WARNING') {
                $insights[] = ['type' => 'battery_intelligence', 'severity' => 'WARNING', 'text' => $batteryInsight['label']];
            }
        }
        // Health score penalties
        foreach (($healthScore['top_penalties'] ?? []) as $penalty) {
            if (in_array($penalty['status'] ?? null, ['CRITICAL', 'WARNING'])) {
                $insights[] = [
                    'type' => 'health_score',
                    'severity' => $penalty['status'],
                    'text' => "{$penalty['label']} menurunkan health score. {$penalty['reason']}",
                ];
            }
        }

        // Dedupe dan batasi 5
        $seen   = [];
        $result = [];
        foreach ($insights as $insight) {
            $key = $insight['text'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[]   = $insight;
            }
            if (count($result) >= 5) break;
        }

        return $result;
    }
}
