<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview PDF — {{ $order->order_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .pdf-frame { width: 100%; height: calc(100vh - 180px); min-height: 600px; border: 1px solid #e2e8f0; border-radius: 0.75rem; }
    </style>
</head>
<body class="bg-slate-100 antialiased">
    <div class="max-w-6xl mx-auto p-4 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 mb-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                    Kembali
                </a>
                <h1 class="text-xl font-bold text-slate-900">Preview PDF — {{ $order->order_number }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Unduh PDF
                </a>
            </div>
        </div>

        <iframe src="{{ $previewUrl }}" class="pdf-frame bg-white shadow-sm" title="Preview PDF"></iframe>
    </div>
</body>
</html>
