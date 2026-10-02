<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * ChatService
 *
 * Mengirim pesan ke OpenAI Chat Completions API menggunakan Guzzle.
 * Mendukung dua mode:
 *   1. complete()  — tunggu respons penuh, return string
 *   2. stream()    — Server-Sent Events (SSE), token dikirim satu per satu
 *
 * Format messages mengikuti OpenAI Chat API:
 *   [
 *     ['role' => 'system',    'content' => '...'],
 *     ['role' => 'user',      'content' => '...'],
 *     ['role' => 'assistant', 'content' => '...'],
 *     ['role' => 'user',      'content' => '...'],
 *   ]
 */
class ChatService
{
    private Client $http;
    private string $apiKey;
    private string $model;
    private int    $maxTokens;

    private const API_PATH = 'chat/completions';
    private const TIMEOUT = 30;

    public function __construct()
    {
        $this->apiKey    = config('services.openai.key', '');
        $this->model     = config('services.openai.model', 'meta-llama/Llama-3.1-8B-Instruct');
        $this->maxTokens = (int) config('services.openai.max_tokens', 1024);

        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        $this->http = new Client([
            'base_uri'        => $baseUrl . '/',
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => 10,
            'headers'         => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Kirim pesan dan tunggu respons penuh.
     * Cocok untuk API sederhana non-streaming.
     *
     * @param  array  $messages  Array of {role, content}
     * @return array{success: bool, content: string, usage: array|null, error: string|null}
     */
    public function complete(array $messages): array
    {
        if (empty($this->apiKey)) {
            return $this->errorResult('OpenAI API key belum dikonfigurasi.');
        }

        try {
            $response = $this->http->post(self::API_PATH, [
                'json' => [
                    'model'       => $this->model,
                    'messages'    => $messages,
                    'max_tokens'  => $this->maxTokens,
                    'temperature' => 0.7,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $content = $body['choices'][0]['message']['content'] ?? '';
            $usage   = $body['usage'] ?? null;

            Log::debug('[ChatService] OpenAI response', [
                'model'  => $this->model,
                'tokens' => $usage,
            ]);

            return [
                'success' => true,
                'content' => trim($content),
                'usage'   => $usage,
                'error'   => null,
            ];

        } catch (RequestException $e) {
            $status  = $e->getResponse()?->getStatusCode();
            $body    = $e->getResponse()?->getBody()?->getContents();
            $decoded = $body ? json_decode($body, true) : [];
            $msg     = $decoded['error']['message'] ?? $e->getMessage();

            Log::error('[ChatService] OpenAI error', ['status' => $status, 'message' => $msg]);

            return $this->errorResult($this->friendlyError($status, $msg));

        } catch (\Throwable $e) {
            Log::error('[ChatService] Unexpected error: ' . $e->getMessage());
            return $this->errorResult('Terjadi kesalahan saat menghubungi AI. Silakan coba lagi.');
        }
    }

    /**
     * Streaming mode — kirim token ke callback satu per satu (SSE).
     *
     * Cara pakai dari controller:
     *   $chatService->stream($messages, function(string $token) {
     *       echo "data: " . json_encode(['token' => $token]) . "\n\n";
     *       ob_flush(); flush();
     *   });
     *
     * @param  array    $messages
     * @param  callable $onToken   fn(string $token): void — dipanggil setiap token
     * @return array{success: bool, error: string|null}
     */
    public function stream(array $messages, callable $onToken): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'OpenAI API key belum dikonfigurasi.'];
        }

        try {
            $response = $this->http->post(self::API_PATH, [
                'json'   => [
                    'model'       => $this->model,
                    'messages'    => $messages,
                    'max_tokens'  => $this->maxTokens,
                    'temperature' => 0.7,
                    'stream'      => true,
                ],
                'stream' => true,
                'timeout'=> 60,
            ]);

            $body   = $response->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $chunk   = $body->read(512);
                $buffer .= $chunk;

                // Proses semua baris lengkap dari buffer
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $line   = trim($line);

                    if ($line === '' || $line === 'data: [DONE]') continue;

                    if (str_starts_with($line, 'data: ')) {
                        $json  = substr($line, 6);
                        $data  = json_decode($json, true);
                        $token = $data['choices'][0]['delta']['content'] ?? '';
                        if ($token !== '') {
                            $onToken($token);
                        }
                    }
                }
            }

            return ['success' => true, 'error' => null];

        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $body   = $e->getResponse()?->getBody()?->getContents();
            $decoded = $body ? json_decode($body, true) : [];
            $msg    = $decoded['error']['message'] ?? $e->getMessage();

            Log::error('[ChatService] Stream error', ['status' => $status, 'message' => $msg]);
            return ['success' => false, 'error' => $this->friendlyError($status, $msg)];

        } catch (\Throwable $e) {
            Log::error('[ChatService] Stream unexpected: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Koneksi ke AI terputus. Silakan coba lagi.'];
        }
    }

    /**
     * Validasi apakah API key sudah dikonfigurasi dan terformat benar.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && strlen($this->apiKey) > 10;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function errorResult(string $message): array
    {
        return ['success' => false, 'content' => '', 'usage' => null, 'error' => $message];
    }

    private function friendlyError(?int $status, string $raw): string
    {
        return match ($status) {
            401 => 'API key tidak valid. Periksa konfigurasi OPENAI_API_KEY.',
            429 => 'Terlalu banyak permintaan ke AI. Tunggu beberapa saat lalu coba lagi.',
            500, 503 => 'Server OpenAI sedang bermasalah. Coba lagi dalam beberapa menit.',
            default  => 'Gagal menghubungi AI: ' . mb_substr($raw, 0, 100),
        };
    }
}
