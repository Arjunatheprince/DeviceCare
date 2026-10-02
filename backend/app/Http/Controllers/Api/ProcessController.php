<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ProcessSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProcessController
 *
 * Menyimpan dan mengambil data top processes dari agent.
 * Menjawab pertanyaan "kenapa CPU/RAM tinggi?" yang tidak tersedia di Task Manager.
 *
 * Endpoints:
 *   POST /api/devices/{device}/processes   — agent kirim data proses
 *   GET  /api/devices/{device}/processes   — dashboard ambil proses terkini
 */
class ProcessController extends Controller
{
    /**
     * POST /api/devices/{device}/processes
     * Terima data top processes dari agent.
     */
    public function store(Request $request, Device $device): JsonResponse
    {
        $validated = $request->validate([
            'timestamp'                     => 'required|string',
            'top_processes.by_cpu'          => 'nullable|array|max:10',
            'top_processes.by_cpu.*.pid'    => 'required|integer',
            'top_processes.by_cpu.*.name'   => 'required|string|max:255',
            'top_processes.by_cpu.*.cpu_percent'    => 'required|numeric',
            'top_processes.by_cpu.*.memory_mb'      => 'required|numeric',
            'top_processes.by_cpu.*.memory_percent' => 'required|numeric',
            'top_processes.by_memory'               => 'nullable|array|max:10',
            'top_processes.by_memory.*.pid'         => 'required|integer',
            'top_processes.by_memory.*.name'        => 'required|string|max:255',
            'top_processes.by_memory.*.cpu_percent'    => 'required|numeric',
            'top_processes.by_memory.*.memory_mb'      => 'required|numeric',
            'top_processes.by_memory.*.memory_percent' => 'required|numeric',
        ]);

        // Simpan sebagai JSON di tabel process_snapshots
        ProcessSnapshot::create([
            'device_id'    => $device->id,
            'recorded_at'  => \Carbon\Carbon::parse($validated['timestamp']),
            'by_cpu'       => $validated['top_processes']['by_cpu']    ?? [],
            'by_memory'    => $validated['top_processes']['by_memory'] ?? [],
        ]);

        // Hapus snapshot lama — hanya simpan 100 terakhir per device
        $count = ProcessSnapshot::where('device_id', $device->id)->count();
        if ($count > 100) {
            ProcessSnapshot::where('device_id', $device->id)
                ->oldest('recorded_at')
                ->limit($count - 100)
                ->delete();
        }

        $device->update(['last_seen_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/devices/{device}/processes
     * Ambil snapshot proses terkini untuk ditampilkan di dashboard.
     */
    public function latest(Device $device): JsonResponse
    {
        $snapshot = ProcessSnapshot::where('device_id', $device->id)
            ->latest('recorded_at')
            ->first();

        if (!$snapshot) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'recorded_at' => $snapshot->recorded_at->toIso8601String(),
                'by_cpu'      => $snapshot->by_cpu,
                'by_memory'   => $snapshot->by_memory,
            ],
        ]);
    }
}
