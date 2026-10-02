<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\RemoteCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteCommandController extends Controller
{
    private const ALLOWED = ['collect_now', 'list_processes', 'cleanup_temp_dry_run'];

    public function index(Device $device): JsonResponse
    {
        $commands = RemoteCommand::where('device_id', $device->id)
            ->latest()
            ->limit(30)
            ->get();

        return response()->json(['data' => $commands]);
    }

    public function store(Request $request, Device $device): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:50',
            'payload' => 'nullable|array',
        ]);

        abort_if(!in_array($validated['command'], self::ALLOWED, true), 422, 'Command tidak diizinkan.');

        $command = RemoteCommand::create([
            'device_id' => $device->id,
            'command' => $validated['command'],
            'payload' => $validated['payload'] ?? [],
            'status' => 'queued',
        ]);

        return response()->json(['success' => true, 'command' => $command], 201);
    }

    public function pending(Device $device): JsonResponse
    {
        $command = RemoteCommand::where('device_id', $device->id)
            ->where('status', 'queued')
            ->oldest()
            ->first();

        if (!$command) return response()->json(['data' => null]);

        $command->update(['status' => 'claimed', 'claimed_at' => now()]);

        return response()->json(['data' => [
            'id' => $command->id,
            'command' => $command->command,
            'payload' => $command->payload ?? [],
        ]]);
    }

    public function complete(Request $request, Device $device, RemoteCommand $command): JsonResponse
    {
        abort_if($command->device_id !== $device->id, 403, 'Command bukan milik device ini.');

        $validated = $request->validate([
            'status' => 'required|string|in:completed,failed,rejected',
            'result' => 'nullable|array',
        ]);

        $command->update([
            'status' => $validated['status'],
            'result' => $validated['result'] ?? [],
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}