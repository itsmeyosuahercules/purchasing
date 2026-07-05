<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }}</title>
    <style>
        @page { margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #595959;
            line-height: 1.15;
            padding: 14mm 14mm 14mm 14mm;
        }

        .brand { color: #1f3863; }
        .muted { color: #595959; }
        .link { color: #467885; text-decoration: underline; }

        table { border-collapse: collapse; }
        .w-full { width: 100%; }

        /* ── Header (match template spacing) ── */
        .header-left { width: 55%; vertical-align: top; padding-right: 8pt; }
        .header-right { width: 45%; vertical-align: top; text-align: right; }

        .title-credentials {
            font-size: 14pt;
            font-weight: bold;
            color: #1f3863;
            line-height: 1.05;
            margin-bottom: 4pt;
        }

        .title-po {
            font-size: 16pt;
            font-weight: bold;
            color: #1f3863;
            line-height: 1.05;
            margin-bottom: 5pt;
        }

        .cred-line {
            font-size: 8pt;
            font-weight: bold;
            color: #595959;
            margin-bottom: 1pt;
            line-height: 1.2;
        }

        .qr-table { margin-top: 3pt; border-collapse: collapse; }
        .qr-table td { padding: 0; vertical-align: top; }
        .qr-table .qr-gap { padding-right: 55pt; }
        .qr-table img { width: 36pt; height: 36pt; display: block; }

        .po-line {
            font-size: 8pt;
            color: #595959;
            margin-bottom: 1pt;
            line-height: 1.2;
        }

        .po-line strong { font-weight: bold; }

        .spacer-sm { height: 6pt; }
        .spacer-md { height: 10pt; }

        /* ── Vendor / Ship To (single table) ── */
        .vendor-ship .panel-head {
            background-color: #1f3863;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            padding: 2pt 4pt;
            border: 0.5pt solid #000000;
            width: 50%;
        }

        .vendor-ship .panel-body {
            border: 0.5pt solid #000000;
            border-top: none;
            padding: 5pt 7pt;
            vertical-align: top;
            width: 50%;
            height: 44pt;
        }

        .vendor-ship .panel-body.centered {
            text-align: center;
            vertical-align: middle;
        }

        .vendor-name {
            font-size: 8pt;
            font-weight: bold;
            color: #1f3863;
            margin-bottom: 2pt;
        }

        .vendor-detail {
            font-size: 8pt;
            line-height: 1.2;
        }

        .ship-text {
            font-size: 10pt;
            font-weight: bold;
            color: #1f3863;
            line-height: 1.2;
        }

        /* ── Order terms grid ── */
        .terms-head {
            background-color: #1f3863;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            padding: 2pt 3pt;
            border: 0.5pt solid #000000;
            width: 20%;
        }

        .terms-val {
            border: 0.5pt solid #000000;
            border-top: none;
            padding: 2pt 4pt;
            text-align: center;
            font-size: 8pt;
            width: 20%;
        }

        /* ── Items (caption + table stay together) ── */
        .item-block {
            page-break-inside: avoid;
            margin-top: 6pt;
        }

        .item-caption {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1f3863;
            margin-bottom: 2pt;
            text-transform: uppercase;
        }

        .item-label {
            background-color: #1f3863;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            padding: 2pt 6pt;
            border: 0.5pt solid #000000;
            width: 23%;
            vertical-align: middle;
        }

        .item-value {
            border: 0.5pt solid #000000;
            border-left: none;
            padding: 2pt 6pt;
            font-size: 8pt;
            color: #595959;
            vertical-align: middle;
        }

        /* ── Totals ── */
        .total-bar td {
            background-color: #1f3863;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            padding: 3pt 7pt;
            border: 0.5pt solid #000000;
        }

        .total-bar .amount {
            text-align: right;
            width: 30%;
        }

        .amount-words {
            margin-top: 6pt;
            font-size: 7.5pt;
            color: #595959;
        }

        /* ── Notes / Terms ── */
        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            color: #1f3863;
            margin: 10pt 0 4pt;
        }

        .notes-body {
            font-size: 8pt;
            color: #595959;
            white-space: pre-wrap;
        }

        .terms-body {
            font-size: 7.5pt;
            color: #595959;
            line-height: 1.35;
        }

        .terms-body p { margin-bottom: 5pt; }

        .footer-note {
            margin-top: 20pt;
            font-size: 8pt;
            font-style: italic;
            color: #595959;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        use App\Support\QuantityFormatter;
        $poDate = $order->poDate();
        $formatDate = fn ($date) => $date ? $date->format('d M Y') : '—';
        $formatMoney = fn ($amount) => number_format((float) $amount, 2, '.', ',');
        $formatQty = fn ($qty) => QuantityFormatter::format($qty, '.');
        $dash = '—';
        $supplierName = $forEmployee ? $order->supplier->alias_name : $order->supplier->real_name;
        $wechatQr = public_path('templates/purchase-order/assets/logo-1.png');
        $whatsappQr = public_path('templates/purchase-order/assets/logo-2.png');
    @endphp

    {{-- Header --}}
    <table class="w-full">
        <tr>
            <td class="header-left">
                @unless($forEmployee)
                    <div class="title-credentials">Credentials</div>
                    @if($companyEmail)
                        <div class="cred-line">Email : <span class="link">{{ $companyEmail }}</span></div>
                    @endif
                    <div class="cred-line">Urgent Contact – Wechat / Whatsapp:</div>
                    <table class="qr-table">
                        <tr>
                            @if(file_exists($wechatQr))
                                <td class="qr-gap"><img src="{{ $wechatQr }}" alt="WeChat"></td>
                            @endif
                            @if(file_exists($whatsappQr))
                                <td><img src="{{ $whatsappQr }}" alt="WhatsApp"></td>
                            @endif
                        </tr>
                    </table>
                @else
                    <div class="title-credentials">{{ $companyName }}</div>
                @endunless
            </td>
            <td class="header-right">
                <div class="title-po">PURCHASE ORDER</div>
                <div class="po-line"><strong>PO Number: {{ $order->order_number }}</strong></div>
                <div class="po-line">PO Date: {{ $formatDate($poDate) }}</div>
                <div class="po-line">Valid Until: {{ $formatDate($order->valid_until) }}</div>
                <div class="po-line">Reference / RFQ No.: {{ $order->reference_rfq_no ?: $dash }}</div>
            </td>
        </tr>
    </table>

    <div class="spacer-md"></div>

    {{-- Vendor / Ship To (one table) --}}
    <table class="w-full vendor-ship">
        <tr>
            <td class="panel-head">VENDOR / SUPPLIER</td>
            <td class="panel-head">SHIP TO</td>
        </tr>
        <tr>
            <td class="panel-body">
                <div class="vendor-name">{{ $supplierName }}</div>
                @unless($forEmployee)
                    <div class="vendor-detail">Attention: {{ $order->supplier->contact_person ?: $dash }}</div>
                    <div class="vendor-detail" style="margin-top:2pt;">{{ $order->supplier->whatsapp }} &nbsp; • &nbsp; {{ $order->supplier->email }}</div>
                @endunless
            </td>
            <td class="panel-body centered">
                <div class="ship-text">{{ $shipTo }}</div>
            </td>
        </tr>
    </table>

    <div class="spacer-md"></div>

    {{-- Order terms --}}
    <table class="w-full">
        <tr>
            <td class="terms-head">Payment Terms</td>
            <td class="terms-head">Delivery Date</td>
            <td class="terms-head">Shipping Method</td>
            <td class="terms-head">Incoterms / FOB</td>
            <td class="terms-head">Currency</td>
        </tr>
        <tr>
            <td class="terms-val">{{ $paymentTerms }}</td>
            <td class="terms-val">{{ $formatDate($order->delivery_date) }}</td>
            <td class="terms-val">{{ $shippingMethod }}</td>
            <td class="terms-val">{{ $incoterms }}</td>
            <td class="terms-val">{{ $currency }}</td>
        </tr>
    </table>

    <div class="spacer-sm"></div>

    {{-- Items --}}
    @foreach($order->items as $i => $item)
        <table class="w-full item-block">
            <tr>
                <td style="padding:0;border:none;">
                    <div class="item-caption">Item {{ $i + 1 }}</div>
                    <table class="w-full">
                        <tr>
                            <td class="item-label">Item Name</td>
                            <td class="item-value">{{ $item->product_name }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Item Content</td>
                            <td class="item-value">{{ $item->item_content ?: $dash }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Native Supplier P/N</td>
                            <td class="item-value">{{ $item->native_supplier_pn ?: $dash }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Brand</td>
                            <td class="item-value">{{ $item->brand ?: $dash }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Description</td>
                            <td class="item-value">{{ $item->description ?: $dash }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Qty</td>
                            <td class="item-value">{{ $formatQty($item->quantity) }}</td>
                        </tr>
                        <tr>
                            <td class="item-label">Unit of Measure</td>
                            <td class="item-value">{{ $item->unit }}</td>
                        </tr>
                        @unless($forEmployee)
                            <tr>
                                <td class="item-label">Unit Price</td>
                                <td class="item-value">{{ $formatMoney($item->price) }}</td>
                            </tr>
                            <tr>
                                <td class="item-label">Amount</td>
                                <td class="item-value">{{ $formatMoney($item->amount()) }}</td>
                            </tr>
                        @endunless
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    @unless($forEmployee)
        <table class="w-full total-bar" style="margin-top:10pt;">
            <tr>
                <td>GRAND TOTAL</td>
                <td class="amount">{{ $formatMoney($order->total()) }}</td>
            </tr>
        </table>

        <div class="amount-words">
            Amount in words: {{ ucfirst($amountInWords) }} Rupiah
        </div>
    @endunless

    <div class="section-heading">Notes / Special Instructions</div>
    <div class="notes-body">{{ $order->notes ?: $dash }}</div>

    @unless($forEmployee)
        <div class="section-heading">Terms &amp; Conditions</div>
        <div class="terms-body">
            @if($termsConditions)
                @foreach(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $termsConditions))) as $line)
                    <p>{{ $line }}</p>
                @endforeach
            @else
                <p>{{ $dash }}</p>
            @endif
        </div>

        <div class="footer-note">
            This is a computer-generated Purchase Order and is valid without signature where authorized electronically.
        </div>
    @endunless
</body>
</html>
