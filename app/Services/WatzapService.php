<?php

namespace App\Services;

use App\Exceptions\WatzapDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WatzapService
{
    public function isConfigured(): bool
    {
        return filled(config('watzap.api_key')) && filled(config('watzap.number_key'));
    }

    public function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized;
    }

    /**
     * Kirim pesan teks (POST /send_message).
     *
     * @return array<string, mixed>
     */
    public function sendText(string $phoneNo, string $message): array
    {
        return $this->post('send_message', [
            'phone_no' => $this->normalizePhone($phoneNo),
            'message' => $message,
        ]);
    }

    /**
     * Kirim file via URL publik (POST /send_file_url) — untuk PDF PO.
     *
     * @return array<string, mixed>
     */
    public function sendFileUrl(string $phoneNo, string $fileUrl, ?string $message = null, ?string $filename = null): array
    {
        $payload = [
            'phone_no' => $this->normalizePhone($phoneNo),
            'url' => $fileUrl,
        ];

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        if ($filename !== null && $filename !== '') {
            $payload['filename'] = $filename;
        }

        return $this->post('send_file_url', $payload, (int) config('watzap.file_timeout', 120));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload, ?int $timeout = null): array
    {
        if (! $this->isConfigured()) {
            throw new WatzapDeliveryException('WatZap belum dikonfigurasi (API Key / Number Key kosong).');
        }

        $payload['api_key'] = config('watzap.api_key');
        $payload['number_key'] = config('watzap.number_key');

        $url = config('watzap.base_url').'/'.ltrim($endpoint, '/');
        $timeout ??= (int) config('watzap.timeout', 30);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::warning('WatZap API timeout / koneksi gagal', [
                'endpoint' => $endpoint,
                'timeout' => $timeout,
                'message' => $e->getMessage(),
            ]);

            throw new WatzapDeliveryException(
                'WatZap tidak merespons tepat waktu (timeout '.$timeout.' detik). Pesan/file mungkin tetap terkirim — cek WhatsApp supplier.',
                null,
            );
        }

        $body = $response->json() ?? [];

        if (! $this->responseIndicatesSuccess($response, $body)) {
            Log::warning('WatZap API gagal', [
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
                'response' => $this->sanitizeResponseForLog($body),
            ]);

            throw new WatzapDeliveryException(
                $this->extractErrorMessage($body, $response),
                $response->status(),
                $body,
            );
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function responseIndicatesSuccess(Response $response, array $body): bool
    {
        if (! $response->successful()) {
            return false;
        }

        if (isset($body['success']) && $body['success'] === false) {
            return false;
        }

        if (isset($body['ack'])) {
            $ack = strtolower((string) $body['ack']);

            if (str_contains($ack, 'fatal') || str_contains($ack, 'fail') || str_contains($ack, 'error')) {
                return false;
            }

            if (str_contains($ack, 'success')) {
                return true;
            }
        }

        if (array_key_exists('status', $body)) {
            $status = (string) $body['status'];

            if (preg_match('/^[1-9]\d{3}$/', $status) && ! in_array($status, ['200'], true)) {
                return false;
            }
        }

        if (array_key_exists('status', $body)) {
            $status = $body['status'];

            if ($status === true || $status === 200 || $status === '200') {
                return true;
            }

            if (filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
                return true;
            }

            if (in_array($status, [false, 'false', '0', 0, 'error', 'failed', 'fail'], true)) {
                return false;
            }
        }

        if (isset($body['success']) && filter_var($body['success'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $message = strtolower((string) ($body['message'] ?? ''));

        foreach (['success', 'berhasil', 'deliver', 'sent', 'terkirim', 'queued'] as $hint) {
            if (str_contains($message, $hint)) {
                return true;
            }
        }

        return $message === '' && empty($body['error']);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractErrorMessage(array $body, Response $response): string
    {
        $message = null;

        foreach (['message', 'msg', 'error'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                $message = $body[$key];
                break;
            }
        }

        $message ??= 'WatZap mengembalikan HTTP '.$response->status();

        if (($body['status'] ?? null) === '1005' || ($body['ack'] ?? null) === 'fatal_error') {
            $message .= ' — WatZap gagal mengunduh PDF. Buka URL PDF di browser (signed link) untuk cek error server.';
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function sanitizeResponseForLog(array $body): array
    {
        unset($body['api_key'], $body['number_key']);

        return $body;
    }
}
