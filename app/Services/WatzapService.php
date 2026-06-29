<?php

namespace App\Services;

use App\Exceptions\WatzapDeliveryException;
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
    public function sendFileUrl(string $phoneNo, string $fileUrl, ?string $message = null): array
    {
        $payload = [
            'phone_no' => $this->normalizePhone($phoneNo),
            'url' => $fileUrl,
        ];

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return $this->post('send_file_url', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new WatzapDeliveryException('WatZap belum dikonfigurasi (API Key / Number Key kosong).');
        }

        $payload['api_key'] = config('watzap.api_key');
        $payload['number_key'] = config('watzap.number_key');

        $url = config('watzap.base_url').'/'.ltrim($endpoint, '/');

        $response = Http::timeout((int) config('watzap.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

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

            if (str_contains($ack, 'success')) {
                return true;
            }

            if (str_contains($ack, 'fail') || str_contains($ack, 'error')) {
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
        foreach (['message', 'msg', 'error'] as $key) {
            if (! empty($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        return 'WatZap mengembalikan HTTP '.$response->status();
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
