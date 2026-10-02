<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * POST /api/devices/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_key'       => 'required|string|max:255',
            'device_name'      => 'required|string|max:255',
            'manufacturer'     => 'nullable|string|max:255',
            'model'            => 'nullable|string|max:255',
            'cpu'              => 'nullable|string|max:255',
            'gpu'              => 'nullable|string|max:255',
            'ram_total_gb'     => 'nullable|numeric',
            'disk_total_gb'    => 'nullable|numeric',
            'operating_system' => 'nullable|string|max:255',
            'os_version'       => 'nullable|string|max:500',
            'agent_version'    => 'nullable|string|max:50',
        ]);

        $device = Device::updateOrCreate(
            ['device_key' => $validated['device_key']],
            array_merge($validated, ['last_seen_at' => now()])
        );

        return response()->json([
            'success'   => true,
            'device_id' => $device->id,
            'message'   => $device->wasRecentlyCreated ? 'Device registered.' : 'Device updated.',
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * GET /api/devices
     */
    public function index(): JsonResponse
    {
        $devices = Device::where('is_active', true)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn ($d) => [
                'id'           => $d->id,
                'device_name'  => $d->device_name,
                'manufacturer' => $d->manufacturer,
                'model'        => $d->model,
                'cpu'          => $d->cpu,
                'ram_total_gb' => $d->ram_total_gb,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                'is_online'    => $d->isOnline(),
            ]);

        return response()->json(['data' => $devices]);
    }

    /**
     * GET /api/devices/{device}
     */
    public function show(Device $device): JsonResponse
    {
        $latest = $device->latestReading();

        return response()->json([
            'data' => [
                'id'               => $device->id,
                'device_name'      => $device->device_name,
                'manufacturer'     => $device->manufacturer,
                'model'            => $device->model,
                'cpu'              => $device->cpu,
                'gpu'              => $device->gpu,
                'ram_total_gb'     => $device->ram_total_gb,
                'disk_total_gb'    => $device->disk_total_gb,
                'operating_system' => $device->operating_system,
                'os_version'       => $device->os_version,
                'last_seen_at'     => $device->last_seen_at?->toIso8601String(),
                'is_online'        => $device->isOnline(),
                'latest_reading'   => $latest?->toSummary(),
            ],
        ]);
    }
}
