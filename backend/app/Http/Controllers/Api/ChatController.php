<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AiContextService;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ChatController
 *
 * Endpoints:
 *   POST /api/devices/{device}/chat         → streaming SSE response
 *   POST /api/devices/{device}/chat/simple  → non-streaming (fallback)
 *   GET  /api/devices/{device}/chat/context → ringkasan konteks yang tersedia
 *   GET  /api/devices/{device}/chat/starters → pertanyaan starter untuk UI
 */
class ChatController extends Controller
{
    /**
     * Starter questions yang ditampilkan di UI chat.
     * Kontekstual dan relevan untuk monitoring hardware.
     */
    private const STARTER_QUESTIONS = [
        ['icon' => '🌡️', 'text' => 'Kenapa laptop saya panas?'],
        ['icon' => '📊', 'text' => 'Bagaimana kondisi laptop saya secara keseluruhan?'],
        ['icon' => '🔋', 'text' => 'Apakah baterai saya dalam kondisi baik?'],
        ['icon' => '💾', 'text' => 'Apakah RAM saya cukup untuk pemakaian sehari-hari?'],
        ['icon' => '⚡', 'text' => 'Apa yang perlu saya lakukan untuk menjaga laptop tetap sehat?'],
        ['icon' => '📈', 'text' => 'Apakah ada tren yang perlu dikhawatirkan minggu ini?'],
        ['icon' => '🖥️', 'text' => 'Kenapa laptop saya terasa lambat?'],
        ['icon' => '💿', 'text' => 'Apakah storage saya perlu dibersihkan?'],
    ];

    public function __construct(
        private readonly AiContextService $contextService,
        private readonly ChatService      $chatService,
        private readonly BatteryIntelligenceService $batteryIntelligenceService,
        private readonly ProcessInsightService $processInsightService,
        private readonly RootCauseAnalysisService $rootCauseService,
        private readonly StorageAnalysisService $storageAnalysisService,
    ) {}

    /**
     * POST /api/devices/{device}/chat
     *
     * Streaming SSE endpoint — token dikirim satu per satu.
     * Frontend menggunakan EventSource atau fetch dengan ReadableStream.
     *
     * Request body:
     *   {
     *     "message": "Kenapa laptop saya panas?",
     *     "history": [                          // opsional, max 10 pesan
     *       {"role": "user",      "content": "..."},
     *       {"role": "assistant", "content": "..."}
     *     ]
     *   }
     */
    public function stream(Request $request, Device $device): StreamedResponse
    {
        // Cek konfigurasi sebelum memulai streaming — lebih baik gagal cepat
        // daripada membuka koneksi SSE lalu mengirim error di tengah stream
        if (!$this->chatService->isConfigured()) {
            return new StreamedResponse(function () {
                if (ob_get_level() > 0) ob_end_clean();
                $this->sseEvent('error', ['message' => 'AI Assistant belum dikonfigurasi. Hubungi administrator.']);
                $this->sseEvent('done',  ['status'  => 'error']);
            }, Response::HTTP_OK, [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $validated = $request->validate([
            'message'         => 'required|string|max:1000',
            'history'         => 'nullable|array|max:10',
            'history.*.role'  => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string|max:2000',
        ]);

        $userMessage = trim($validated['message']);
        $history     = $validated['history'] ?? [];

        // Bangun messages array untuk OpenAI
        $systemPrompt = $this->contextService->buildSystemPrompt($device)
            . $this->buildToolContext($device, $userMessage);
        $messages     = $this->buildMessages($systemPrompt, $history, $userMessage);

        return new StreamedResponse(function () use ($messages) {
            // Header SSE harus dikirim sebelum output apapun
            if (ob_get_level() > 0) ob_end_clean();

            // Kirim event "start" agar frontend tahu streaming dimulai
            $this->sseEvent('start', ['status' => 'streaming']);

            $result = $this->chatService->stream(
                $messages,
                function (string $token) {
                    // Kirim setiap token sebagai SSE event
                    $this->sseEvent('token', ['token' => $token]);
                }
            );

            if (!$result['success']) {
                $this->sseEvent('error', ['message' => $result['error']]);
            }

            // Event "done" menandakan streaming selesai
            $this->sseEvent('done', ['status' => 'complete']);

        }, Response::HTTP_OK, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',   // matikan buffering di Nginx
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * POST /api/devices/{device}/chat/simple
     *
     * Non-streaming fallback — tunggu respons penuh lalu return JSON.
     * Digunakan jika browser tidak support SSE atau untuk testing.
     */
    public function simple(Request $request, Device $device): JsonResponse
    {
        $validated = $request->validate([
            'message'           => 'required|string|max:1000',
            'history'           => 'nullable|array|max:10',
            'history.*.role'    => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string|max:2000',
        ]);

        if (!$this->chatService->isConfigured()) {
            return response()->json([
                'success' => false,
                'error'   => 'AI Assistant belum dikonfigurasi. Hubungi administrator.',
            ], 503);
        }

        $userMessage  = trim($validated['message']);
        $history      = $validated['history'] ?? [];
        $systemPrompt = $this->contextService->buildSystemPrompt($device)
            . $this->buildToolContext($device, $userMessage);
        $messages     = $this->buildMessages($systemPrompt, $history, $userMessage);

        $result = $this->chatService->complete($messages);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'],
            ], 502);
        }

        return response()->json([
            'success'  => true,
            'response' => $result['content'],
            'usage'    => $result['usage'],
        ]);
    }

    /**
     * GET /api/devices/{device}/chat/context
     *
     * Kembalikan ringkasan konteks yang dimiliki AI untuk device ini.
     * Ditampilkan di UI sebagai info "AI tahu tentang: ..."
     */
    public function context(Device $device): JsonResponse
    {
        $summary      = $this->contextService->buildContextSummary($device);
        $isConfigured = $this->chatService->isConfigured();

        return response()->json([
            'device_id'    => $device->id,
            'ai_ready'     => $isConfigured && $summary['has_reading'],
            'ai_configured'=> $isConfigured,
            'context'      => $summary,
            'model'        => config('services.openai.model', 'gpt-4o-mini'),
        ]);
    }

    /**
     * GET /api/devices/{device}/chat/starters
     *
     * Kembalikan daftar starter questions untuk UI.
     * Beberapa pertanyaan disesuaikan berdasarkan kondisi device saat ini.
     */
    public function starters(Device $device): JsonResponse
    {
        $starters = self::STARTER_QUESTIONS;

        // Tambah pertanyaan kontekstual jika ada kondisi spesifik
        $latest = $device->latestReading();
        if ($latest) {
            $contextual = [];

            if ($latest->cpu_status === 'CRITICAL' || $latest->cpu_status === 'WARNING') {
                $contextual[] = ['icon' => '🔴', 'text' => 'CPU saya dalam kondisi ' . strtolower($latest->cpu_status) . ', apa yang harus dilakukan?'];
            }
            if ($latest->ram_status === 'CRITICAL') {
                $contextual[] = ['icon' => '🔴', 'text' => 'RAM saya hampir penuh, apa yang harus saya tutup?'];
            }
            if ($latest->disk_status === 'WARNING' || $latest->disk_status === 'CRITICAL') {
                $contextual[] = ['icon' => '💿', 'text' => 'Storage saya hampir penuh, bagaimana cara membersihkannya?'];
            }
            if ($latest->battery_status === 'WARNING') {
                $contextual[] = ['icon' => '🔋', 'text' => 'Baterai saya hampir habis, apakah aman terus dipakai?'];
            }
            if ($latest->health_score !== null && $latest->health_score < 60) {
                $contextual[] = ['icon' => '⚠️', 'text' => 'Health score saya rendah (' . $latest->health_score . '/100), apa masalahnya?'];
            }

            // Sisipkan pertanyaan kontekstual di awal
            if (!empty($contextual)) {
                $starters = array_merge($contextual, $starters);
            }
        }

        return response()->json([
            'device_id' => $device->id,
            'starters'  => array_slice($starters, 0, 8), // maks 8 untuk UI
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildToolContext(Device $device, string $message): string
    {
        $q = mb_strtolower($message);
        $context = [];
        if (str_contains($q, 'lambat') || str_contains($q, 'kenapa') || str_contains($q, 'penyebab')) {
            $context['root_cause'] = $this->rootCauseService->analyze($device);
            $context['process'] = $this->processInsightService->analyze($device, 15);
        }
        if (str_contains($q, 'storage') || str_contains($q, 'disk') || str_contains($q, 'penuh') || str_contains($q, 'bersih')) {
            $context['storage'] = $this->storageAnalysisService->analyze($device, 14);
        }
        if (str_contains($q, 'baterai') || str_contains($q, 'battery') || str_contains($q, 'charger')) {
            $context['battery'] = $this->batteryIntelligenceService->analyze($device, 24);
        }

        if (empty($context)) return '';

        return "\n\nKonteks tool terbaru (gunakan sebagai fakta data, jangan sebut sebagai JSON mentah):\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Susun array messages untuk OpenAI dari system prompt + history + pesan baru.
     */
    private function buildMessages(string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Sertakan history percakapan (maks 10 pesan terakhir untuk hemat token)
        foreach (array_slice($history, -10) as $h) {
            if (!empty($h['content'])) {
                $messages[] = [
                    'role'    => $h['role'],
                    'content' => $h['content'],
                ];
            }
        }

        // Pesan user terbaru
        $messages[] = [
            'role'    => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Tulis satu SSE event ke output.
     */
    private function sseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
    }
}
