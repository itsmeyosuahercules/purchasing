<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WatZap WA Unofficial API
    |--------------------------------------------------------------------------
    |
    | Credentials dari app.watzap.id → Integrations (API Key) dan WhatsApp tab API
    | (Number Key). Paket Plus/Premium diperlukan untuk akses REST API.
    |
    | Docs: https://api-docs.watzap.id/
    |
    */

    'enabled' => (bool) env('WATZAP_ENABLED', false),

    'api_key' => env('WATZAP_API_KEY'),

    'number_key' => env('WATZAP_NUMBER_KEY'),

    'base_url' => rtrim(env('WATZAP_BASE_URL', 'https://api.watzap.id/v1'), '/'),

    'timeout' => (int) env('WATZAP_TIMEOUT', 30),

    'file_timeout' => (int) env('WATZAP_FILE_TIMEOUT', 120),

    'send_delay_seconds' => (int) env('WATZAP_SEND_DELAY', 5),

    /*
    | Beri waktu server WatZap mengunduh PDF dari URL publik sebelum file dihapus.
    */
    'file_cleanup_delay_minutes' => (int) env('WATZAP_FILE_CLEANUP_DELAY', 5),

    /*
    | Signed URL untuk PDF yang di-fetch server WatZap. APP_URL harus dapat
    | diakses dari internet (bukan localhost) saat production.
    */
    'pdf_url_ttl_minutes' => (int) env('WATZAP_PDF_URL_TTL', 30),

    /*
    | Lampirkan PDF via send_file_url. null = auto (hanya jika APP_URL HTTPS &
    | bukan domain local). false = teks saja. true = paksa PDF (butuh URL publik HTTPS).
    */
    'attach_pdf' => env('WATZAP_ATTACH_PDF'),

    'queue' => env('WATZAP_QUEUE', 'default'),

];
