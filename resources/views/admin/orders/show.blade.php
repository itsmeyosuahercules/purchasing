<x-app-layout :title="$order->order_number">
    <div class="mb-5">
        <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
            <x-icon name="arrow-left" class="w-4 h-4" /> Kembali ke daftar
        </a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-slate-900">{{ $order->order_number }}</h2>
                <x-badge :status="$order->status" />
            </div>
            <p class="text-sm text-slate-500 mt-1">Dibuat {{ $order->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex gap-2">
            <x-button href="{{ route('admin.orders.pdf.preview', $order) }}" variant="secondary">
                <x-icon name="document" class="w-4 h-4" /> Preview PDF
            </x-button>
            <x-button href="{{ route('admin.orders.pdf.download', $order) }}" variant="secondary">
                Unduh PDF
            </x-button>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            @if($order->status->value !== 'rejected')
                <x-card title="Detail Purchase Order" subtitle="Disimpan ke PDF sebelum approve atau setelahnya.">
                    <form method="POST" action="{{ route('admin.orders.po-details', $order) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <div class="grid md:grid-cols-2 gap-4">
                            <x-input name="reference_rfq_no" label="Reference / RFQ No." :value="$order->reference_rfq_no" />
                            <x-input name="valid_until" type="date" label="Valid Until"
                                     :value="$order->valid_until?->format('Y-m-d')" />
                            <x-input name="delivery_date" type="date" label="Delivery Date"
                                     :value="$order->delivery_date?->format('Y-m-d')" />
                        </div>
                        <x-textarea name="notes" label="Notes / Special Instructions" rows="3" :value="$order->notes" />
                        <x-button type="submit" variant="secondary">Simpan Detail PO</x-button>
                    </form>
                </x-card>
            @endif

            <x-card title="Items" :padding="false">
                <div class="divide-y divide-slate-100">
                    @foreach($order->items as $i => $item)
                        <div class="px-5 py-4">
                            <p class="font-semibold text-slate-900 mb-2">Item {{ $i + 1 }} — {{ $item->product_name }}</p>
                            <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                <div class="sm:col-span-2"><dt class="text-slate-500">Item Content</dt><dd class="text-slate-800 whitespace-pre-wrap">{{ $item->item_content ?: '—' }}</dd></div>
                                <div><dt class="text-slate-500">Native Supplier P/N</dt><dd class="text-slate-800">{{ $item->native_supplier_pn ?: '—' }}</dd></div>
                                <div><dt class="text-slate-500">Brand</dt><dd class="text-slate-800">{{ $item->brand ?: '—' }}</dd></div>
                                <div><dt class="text-slate-500">Unit</dt><dd class="text-slate-800">{{ $item->unit }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-slate-500">Description</dt><dd class="text-slate-800">{{ $item->description ?: '—' }}</dd></div>
                                <div><dt class="text-slate-500">Qty</dt><dd class="text-slate-800 tabular-nums">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</dd></div>
                                <div><dt class="text-slate-500">Unit Price</dt><dd class="text-slate-800 tabular-nums">Rp {{ number_format($item->price, 2, ',', '.') }}</dd></div>
                                <div><dt class="text-slate-500">Amount</dt><dd class="text-slate-900 font-medium tabular-nums">Rp {{ number_format($item->amount(), 2, ',', '.') }}</dd></div>
                            </dl>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 text-right font-semibold text-slate-900">
                    Grand Total: Rp {{ number_format($order->total(), 2, ',', '.') }}
                </div>
            </x-card>

            @if($order->isPending())
                <x-card title="Tindakan">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <form method="POST" action="{{ route('admin.orders.approve', $order) }}"
                              onsubmit="return confirm('Setujui pesanan dan kirim email{{ config('watzap.enabled') ? ' + WhatsApp' : '' }} ke supplier?')">
                            @csrf
                            <x-button type="submit" variant="success">
                                <x-icon name="check" class="w-4 h-4" /> Setujui & Kirim
                            </x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                            @csrf
                            <input name="rejection_reason" placeholder="Alasan penolakan (opsional)" class="field-input flex-1">
                            <x-button type="submit" variant="danger">
                                <x-icon name="x-circle" class="w-4 h-4" /> Tolak
                            </x-button>
                        </form>
                    </div>
                </x-card>
            @endif

            @if($order->status->value === 'approved')
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-50 text-emerald-600">
                                <x-icon name="check" class="w-5 h-5" />
                            </span>
                            <div>
                                <p class="font-medium text-slate-900">Disetujui</p>
                                <p class="text-sm text-slate-500">
                                    oleh {{ $order->approver?->name ?? '—' }} · {{ $order->approved_at?->format('d/m/Y H:i') }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ $order->supplier_emailed_at ? 'Email terkirim '.$order->supplier_emailed_at->format('d/m/Y H:i') : 'Email belum terkirim' }}
                                </p>
                                <p class="text-xs {{ $order->supplier_whatsapp_error ? 'text-red-500' : ($whatsappSending ? 'text-amber-600' : 'text-slate-400') }} mt-0.5">
                                    @if(! config('watzap.enabled'))
                                        WhatsApp API nonaktif (WATZAP_ENABLED=false)
                                    @elseif($whatsappSending ?? false)
                                        WhatsApp sedang dikirim… tunggu ~1–2 menit lalu refresh
                                    @elseif($order->supplier_whatsapp_sent_at)
                                        WhatsApp terkirim {{ $order->supplier_whatsapp_sent_at->format('d/m/Y H:i') }}
                                    @elseif($order->supplier_whatsapp_error)
                                        WhatsApp gagal: {{ \Illuminate\Support\Str::limit($order->supplier_whatsapp_error, 120) }}
                                    @else
                                        WhatsApp belum terkirim
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.orders.resend-email', $order) }}"
                                  onsubmit="return confirm('Kirim ulang email ke supplier{{ $order->supplier_emailed_at ? ' (salinan admin ikut terkirim)' : '' }}?')">
                                @csrf
                                <x-button type="submit" :variant="$order->supplier_emailed_at ? 'secondary' : 'primary'">
                                    <x-icon name="mail" class="w-4 h-4" />
                                    {{ $order->supplier_emailed_at ? 'Kirim Ulang Email' : 'Kirim Email' }}
                                </x-button>
                            </form>
                            <form method="POST" action="{{ route('admin.orders.resend-whatsapp', $order) }}"
                                  onsubmit="this.querySelector('button[type=submit]')?.setAttribute('disabled', 'disabled'); return confirm('Kirim{{ $order->supplier_whatsapp_sent_at ? ' ulang' : '' }} WhatsApp ke supplier?')">
                                @csrf
                                <x-button type="submit" :variant="$order->supplier_whatsapp_sent_at ? 'secondary' : 'primary'">
                                    <x-icon name="whatsapp" class="w-4 h-4" />
                                    {{ $order->supplier_whatsapp_sent_at ? 'Kirim Ulang WhatsApp' : 'Kirim WhatsApp' }}
                                </x-button>
                            </form>
                            @if($order->whatsapp_link)
                                <x-button :href="$order->whatsapp_link" variant="secondary" target="_blank" rel="noopener">
                                    <x-icon name="whatsapp" class="w-4 h-4" /> Buka WA Manual
                                </x-button>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endif

            @if($order->status->value === 'rejected')
                <x-card>
                    <div class="flex items-start gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-full bg-red-50 text-red-600">
                            <x-icon name="x-circle" class="w-5 h-5" />
                        </span>
                        <div>
                            <p class="font-medium text-slate-900">Ditolak</p>
                            <p class="text-sm text-slate-500">{{ $order->rejected_at?->format('d/m/Y H:i') }}</p>
                            @if($order->rejection_reason)
                                <p class="text-sm text-slate-600 mt-1">Alasan: {{ $order->rejection_reason }}</p>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card title="Karyawan">
                <p class="font-medium text-slate-900">{{ $order->user->name }}</p>
                <p class="text-sm text-slate-500">{{ '@'.$order->user->username }}</p>
            </x-card>

            <x-card title="Supplier" subtitle="Hanya terlihat admin">
                <dl class="space-y-2.5 text-sm">
                    <div>
                        <dt class="text-slate-500">Nama Asli</dt>
                        <dd class="font-medium text-slate-900">{{ $order->supplier->real_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Nama Samaran</dt>
                        <dd class="text-slate-700">{{ $order->supplier->alias_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Contact Person</dt>
                        <dd class="text-slate-700">{{ $order->supplier->contact_person ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Email</dt>
                        <dd class="text-slate-700">{{ $order->supplier->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">WhatsApp</dt>
                        <dd class="text-slate-700">{{ $order->supplier->whatsapp }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
