<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    /**
     * GET /api/devices/{device}/history
     *
     * Query params:
     *   period = hour | day | week  (default: day)
     *   days   = 1..30              (default: 7)
     *   metric = health_score | cpu_usage | cpu_temperature |
     *            ram_usage | disk_usage | battery_percentage |
     *            gpu_temperature     (default: health_score)
     */
    public function index(Request $request, Device $device): JsonResponse
    {
        $period = $request->query('period', 'day');
        $days   = min((int) $request->query('days', 7), 30);
        $metric = $this->sanitizeMetric($request->query('metric', 'health_score'));
        $since  = now()->subDays($days);

        $groupExpr = match ($period) {
            'hour' => "DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')",
            'week' => "DATE_FORMAT(recorded_at, '%x-W%v')",
            default => "DATE(recorded_at)",
        };

        $rows = DB::table('sensor_readings')
            ->select(
                DB::raw("$groupExpr AS period"),
                DB::raw("ROUND(AVG($metric), 1) AS avg_val"),
                DB::raw("ROUND(MIN($metric), 1) AS min_val"),
                DB::raw("ROUND(MAX($metric), 1) AS max_val"),
                DB::raw("COUNT(*) AS samples"),
                DB::raw("MIN(recorded_at) AS period_start"),
            )
            ->where('device_id', $device->id)
            ->where('recorded_at', '>=', $since)
            ->whereNotNull($metric)
            ->groupBy(DB::raw($groupExpr))
            ->orderBy('period_start')
            ->get()
            ->map(fn ($r) => [
                'period'  => $r->period,
                'ts'      => $r->period_start,
                'avg'     => (float) $r->avg_val,
                'min'     => (float) $r->min_val,
                'max'     => (float) $r->max_val,
                'samples' => (int) $r->samples,
            ]);

        $trend = $this->calcTrend($rows->toArray());

        return response()->json([
            'device_id' => $device->id,
            'metric'    => $metric,
            'period'    => $period,
            'days'      => $days,
            'data'      => $rows,
            'summary'   => [
                'avg'         => $rows->avg('avg') ? round($rows->avg('avg'), 1) : null,
                'min'         => $rows->min('min'),
                'max'         => $rows->max('max'),
                'trend'       => $trend,
                'trend_label' => $this->trendLabel($trend, $metric),
            ],
        ]);
    }

    /**
     * GET /api/devices/{device}/history/multi
     * Semua metrik utama dalam satu request (untuk grafik overview).
     */
    public function multi(Request $request, Device $device): JsonResponse
    {
        $days  = min((int) $request->query('days', 7), 30);
        $since = now()->subDays($days);

        $rows = DB::table('sensor_readings')
            ->select(
                DB::raw("DATE(recorded_at) AS day"),
                DB::raw("ROUND(AVG(health_score),1)       AS health_score"),
                DB::raw("ROUND(AVG(cpu_usage),1)          AS cpu_usage"),
                DB::raw("ROUND(AVG(cpu_temperature),1)    AS cpu_temp"),
                DB::raw("ROUND(AVG(ram_usage),1)          AS ram_usage"),
                DB::raw("ROUND(AVG(gpu_temperature),1)    AS gpu_temp"),
                DB::raw("ROUND(AVG(disk_usage),1)         AS disk_usage"),
                DB::raw("ROUND(AVG(battery_percentage),1) AS battery"),
                DB::raw("COUNT(*) AS samples"),
            )
            ->where('device_id', $device->id)
            ->where('recorded_at', '>=', $since)
            ->groupBy(DB::raw("DATE(recorded_at)"))
            ->orderBy('day')
            ->get();

        return response()->json(['data' => $rows, 'days' => $days]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function sanitizeMetric(string $m): string
    {
        return in_array($m, [
            'health_score', 'cpu_usage', 'cpu_temperature',
            'ram_usage', 'disk_usage', 'battery_percentage',
            'gpu_usage', 'gpu_temperature',
        ]) ? $m : 'health_score';
    }

    private function calcTrend(array $rows): ?float
    {
        if (count($rows) < 4) return null;
        $mid   = (int) floor(count($rows) / 2);
        $first = collect(array_slice($rows, 0, $mid))->avg('avg');
        $last  = collect(array_slice($rows, $mid))->avg('avg');
        return $first > 0 ? round((($last - $first) / $first) * 100, 1) : null;
    }

    private function trendLabel(?float $trend, string $metric): string
    {
        if ($trend === null) return 'Data belum cukup untuk analisis trend.';
        $higherIsBetter = in_array($metric, ['health_score', 'battery_percentage']);
        $abs = abs($trend);
        if ($abs < 2) return 'Stabil — tidak ada perubahan signifikan.';
        if ($trend > 0) {
            return $higherIsBetter
                ? "Meningkat {$abs}% — kondisi membaik."
                : "Meningkat {$abs}% — perlu diperhatikan.";
        }
        return $higherIsBetter
            ? "Menurun {$abs}% — kondisi memburuk."
            : "Menurun {$abs}% — kondisi membaik.";
    }
}
