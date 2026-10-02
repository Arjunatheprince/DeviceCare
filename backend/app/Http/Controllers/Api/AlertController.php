<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * GET /api/devices/{device}/alerts
     * Ambil alert aktif (belum resolved) — untuk polling dari browser.
     *
     * Query params:
     *   since   = ISO timestamp — hanya alert setelah waktu ini
     *   limit   = 20 (default)
     */
    public function index(Request $request, Device $device): JsonResponse
    {
        $query = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->orderByDesc('created_at');

        if ($since = $request->query('since')) {
            $query->where('created_at', '>', $since);
        }

        $alerts = $query->limit((int) $request->query('limit', 20))
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'component'   => $a->component,
                'severity'    => $a->severity,
                'title'       => $a->title,
                'message'     => $a->message,
                'suggestions' => $a->suggestions ?? [],
                'created_at'  => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $alerts]);
    }

    /**
     * GET /api/devices/{device}/alerts/unread-count
     * Jumlah alert aktif — untuk badge di nav.
     */
    public function unreadCount(Device $device): JsonResponse
    {
        $count = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $hasCritical = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->where('severity', 'CRITICAL')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        return response()->json([
            'count'       => $count,
            'has_critical' => $hasCritical,
        ]);
    }

    /**
     * POST /api/alerts/{alert}/resolve  (atau /api/devices/{device}/alerts/{alert}/resolve)
     * Tandai alert sebagai resolved.
     * Validasi alert milik device yang benar.
     */
    public function resolve(Request $request, Alert $alert): JsonResponse
    {
        abort_if($alert->is_resolved, 422, 'Alert sudah di-resolve sebelumnya.');

        // Jika dipanggil dari route /devices/{device}/alerts/{alert}/resolve,
        // validasi bahwa alert memang milik device tersebut
        if ($request->route('device')) {
            $deviceId = $request->route('device')->id ?? $request->route('device');
            abort_if($alert->device_id != $deviceId, 403, 'Alert ini bukan milik device yang diminta.');
        }

        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/devices/{device}/alerts/resolve-all
     * Resolve semua alert aktif untuk device ini.
     */
    public function resolveAll(Device $device): JsonResponse
    {
        $count = Alert::where('device_id', $device->id)
            ->where('is_resolved', false)
            ->update(['is_resolved' => true, 'resolved_at' => now()]);

        return response()->json(['success' => true, 'resolved' => $count]);
    }
}
