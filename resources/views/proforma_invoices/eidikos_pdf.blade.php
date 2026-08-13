<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.eidikos_styles')
</head>
<body class="eidikos-document">
@php
    $m = $money;
    $fmt = fn ($a) => $m['currency'].' '.number_format((float) $a, 2);
    $fmtBase = fn ($a) => $m['base'].' '.number_format((float) $a, 2);

    $orgName = $settings['organization_name'] ?? 'Eidikos Cert.';
    $logoAbs = ! empty($settings['logo_path']) && file_exists(storage_path('app/public/'.$settings['logo_path']))
        ? storage_path('app/public/'.$settings['logo_path']) : null;

    $clientName = $client['company_name'] ?? $invoice->client?->company_name;
    $siteName = $invoice->site_name ?: $clientName;
    $siteAddress = $client['address'] ?? null;

    $mode = $invoice->charge_presentation ?? 'itemized';
    $items = $invoice->items;
    $firstItem = $items->first();
    $serviceName = $invoice->charge_title ?: ($firstItem?->service?->short_name ?: ($firstItem?->description ?: 'Service'));
    $singleCharge = in_array($mode, ['consolidated', 'component_breakdown'], true);
    $hasStandards = filled($invoice->standards_snapshot['items'] ?? null);
    $standardsLabel = $invoice->standards_snapshot['category']['selection_label'] ?? 'Standards / Scope';
    $scopeList = collect($firstItem?->scope_items ?: [])->filter()->values();
    if ($scopeList->isEmpty()) {
        $scopeList = collect($invoice->standards_snapshot['items'] ?? [])->pluck('name')->filter()->values();
    }
    $vatShown = ($invoice->vat_treatment ?? null) === 'add' && (float) ($invoice->vat_amount ?? 0) > 0 && ($invoice->show_vat_separately ?? true);

    $terms = collect(preg_split('/\r\n|\r|\n/', (string) ($settings['invoice_payment_terms'] ?? '')))
        ->map(fn ($l) => trim($l, " \t\n\r\0\x0B-*•"))->filter()->values();
    if ($terms->isEmpty()) {
        $terms = collect([
            'Full payment is required in advance.',
            'Amount is exclusive of VAT and taxes unless otherwise stated.',
            'Payment by account payee cheque or bank transfer only.',
            'This invoice is valid for 30 days from the date of issue.',
        ]);
    }

    $bankRows = array_filter([
        'Beneficiary' => $bank['beneficiary_name'] ?? null,
        'Bank' => $bank['bank_name'] ?? null,
        'Branch' => $bank['branch'] ?? null,
        'Account No.' => $bank['account_number'] ?? null,
        'SWIFT' => $bank['swift_code'] ?? null,
    ], fn ($v) => filled($v));

    $scheduleNote = $settings['invoice_default_notes'] ?? 'Further scheduling of the work plan will take place following confirmation of the applicable payment.';
    $note = $settings['pdf_note'] ?? 'This is a computer-generated invoice and does not require an authorized signature.';
    $contactPhone = $settings['phone'] ?? null;
    $contactName = $settings['prepared_by_name'] ?? null;
    $contactDesignation = $settings['prepared_by_designation'] ?? null;
@endphp

{{-- Fixed brand header (repeats each page) --}}
<div class="e-header">
    <table class="eh-table">
        <tr>
            <td class="eh-logo-cell">
                @if ($logoAbs)
                    <img class="eh-logo" src="{{ $logoAbs }}" alt="">
                @else
                    <div class="eh-mark">EC</div>
                @endif
            </td>
            <td class="eh-brand-cell">
                <div class="eh-name">EIDIKOS <span>CERT.</span></div>
                <div class="eh-addr">
                    {{ $settings['office_address'] ?? '' }}<br>
                    {{ $settings['email'] ?? '' }}
                </div>
            </td>
        </tr>
    </table>
</div>
<div class="e-header-rule"></div>

{{-- Fixed footer (no QR, no verification) --}}
<div class="e-footer">
    <div class="ef-name">EIDIKOS <span>CERT.</span></div>
    <div class="ef-line">
        {{ $settings['office_address'] ?? '' }}<br>
        {{ implode('  |  ', array_filter([$settings['phone'] ?? null, $settings['email'] ?? null, $settings['website'] ?? null])) }}
    </div>
</div>

<main>
    <div class="e-title">
        <h1>Proforma Invoice</h1>
        <div class="e-title-sub"></div>
    </div>

    {{-- Reference (left) + Client (right) --}}
    <table class="e-info">
        <tr>
            <td>
                <div class="e-info-h">Document</div>
                <table class="e-kv">
                    <tr><td class="e-k">Reference No.</td><td class="e-v">{{ $invoice->reference_no ?: '—' }}</td></tr>
                    <tr><td class="e-k">Invoice Ref. Number</td><td class="e-v">{{ $invoice->number }}</td></tr>
                    <tr><td class="e-k">Date</td><td class="e-v">{{ $invoice->date->format('d M Y') }}</td></tr>
                </table>
            </td>
            <td>
                <div class="e-info-h">Client</div>
                <table class="e-kv">
                    <tr><td class="e-k">Client Name</td><td class="e-v">{{ $clientName }}</td></tr>
                    <tr><td class="e-k">Site Name</td><td class="e-v">{{ $siteName }}</td></tr>
                    @if (filled($siteAddress))<tr><td class="e-k">Site Address</td><td class="e-v">{{ $siteAddress }}</td></tr>@endif
                    @if (filled($client['contact_person'] ?? null))<tr><td class="e-k">Attention</td><td class="e-v">{{ $client['contact_person'] }}</td></tr>@endif
                    @if (filled($client['designation'] ?? null))<tr><td class="e-k">Designation</td><td class="e-v">{{ $client['designation'] }}</td></tr>@endif
                    @if (filled($client['email'] ?? null))<tr><td class="e-k">Email</td><td class="e-v">{{ $client['email'] }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Commercial table: S/N | DESCRIPTION OF PARTICULAR | AMOUNT --}}
    <table class="e-comm">
        <thead>
            <tr>
                <th class="e-c-sn">S/N</th>
                <th>Description of Particular</th>
                <th class="e-c-amt">Amount ({{ $m['currency'] }})</th>
            </tr>
        </thead>
        <tbody>
            @if ($singleCharge)
                <tr>
                    <td class="e-c-sn">1</td>
                    <td>
                        <div class="e-p-title">{{ $serviceName }}</div>
                        @if ($hasStandards)
                            <div class="e-inc">{{ $standardsLabel }}:</div>
                            <ul class="e-inc-list">@foreach ($scopeList as $s)<li>{{ $s }}</li>@endforeach</ul>
                            @if (filled($firstItem?->description))<div class="e-p-desc">{{ $firstItem->description }}</div>@endif
                        @elseif ($mode === 'consolidated')
                            @if (filled($firstItem?->description) && $firstItem->description !== $serviceName)
                                <div class="e-p-desc">{{ $firstItem->description }}</div>
                            @endif
                        @else
                            <div class="e-inc">Including:</div>
                            <ul class="e-inc-list">
                                @foreach ($firstItem?->scope_items ?? [] as $s)<li>{{ $s }}</li>@endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="e-c-amt">{{ $fmt($firstItem?->amount ?? $m['subtotal']) }}</td>
                </tr>
            @else
                @foreach ($items as $item)
                    <tr>
                        <td class="e-c-sn">{{ $loop->iteration }}</td>
                        <td>
                            <div class="e-p-title">{{ $item->service?->short_name ?: $item->description }}</div>
                            @if ($item->service?->short_name && filled($item->description) && $item->description !== $item->service?->short_name)
                                <div class="e-p-desc">{{ $item->description }}</div>
                            @endif
                            @if (! empty($item->scope_items))
                                <div class="e-inc">Including:</div>
                                <ul class="e-inc-list">@foreach ($item->scope_items as $s)<li>{{ $s }}</li>@endforeach</ul>
                            @endif
                        </td>
                        <td class="e-c-amt">{{ $fmt($item->amount) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    {{-- Currency summary (dual USD/BDT when a conversion rate is set) --}}
    <table class="e-sum-wrap">
        <tr>
            <td class="e-sum-spacer"></td>
            <td>
                <table class="e-sum">
                    <tr><td class="e-sum-l">Net Amount ({{ $m['currency'] }})</td><td class="e-sum-r">{{ $fmt($m['subtotal']) }}</td></tr>
                    @if ((float) $invoice->adjustment !== 0.0)
                        <tr><td class="e-sum-l">Adjustment</td><td class="e-sum-r">{{ $fmt($invoice->adjustment) }}</td></tr>
                    @endif
                    @if ($vatShown)
                        <tr><td class="e-sum-l">VAT @ {{ rtrim(rtrim(number_format((float) $invoice->vat_rate, 3), '0'), '.') }}%</td><td class="e-sum-r">{{ $fmt($m['vat']) }}</td></tr>
                    @endif
                    <tr class="e-sum-total"><td class="e-sum-l">Total Payable ({{ $m['currency'] }})</td><td class="e-sum-r">{{ $fmt($m['total']) }}</td></tr>
                    @if ($m['dual'])
                        <tr class="e-sum-base"><td class="e-sum-l">Net Amount ({{ $m['base'] }})</td><td class="e-sum-r">{{ $fmtBase($m['base_subtotal']) }}</td></tr>
                        <tr class="e-sum-basetotal"><td class="e-sum-l">Total Payable ({{ $m['base'] }})</td><td class="e-sum-r">{{ $fmtBase($m['base_total']) }}</td></tr>
                        <tr><td class="e-rate" colspan="2">Conversion Rate: 1 {{ $m['currency'] }} = {{ $m['base'] }} {{ number_format($m['rate'], 2) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="e-words"><strong>In Words:</strong> {{ $amountInWords }}</div>
    @if (filled($scheduleNote))
        <div class="e-schedule">{{ $scheduleNote }}</div>
    @endif

    {{-- Payment details: Bank (left) | Terms (right) --}}
    <div class="e-sec"><div class="e-sec-h">Payment Details</div></div>
    <table class="e-pay">
        <tr>
            <td class="e-pay-bank">
                <div class="e-col-h">Bank Details</div>
                <table class="e-bank">
                    @foreach ($bankRows as $k => $v)
                        <tr><td class="e-k">{{ $k }}</td><td>{{ $v }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td class="e-pay-terms">
                <div class="e-col-h">Terms &amp; Conditions</div>
                <ul class="e-terms-list">
                    @foreach ($terms as $t)<li>{{ $t }}</li>@endforeach
                </ul>
            </td>
        </tr>
    </table>

    {{-- Contact (left) + computer-generated note (right) --}}
    <table class="e-foot-row">
        <tr>
            <td style="width:60%">
                <div class="e-contact-h">Contact</div>
                @if (filled($contactName))<div class="e-contact-name">{{ $contactName }}</div>@endif
                <div class="e-contact-line">
                    @if (filled($contactDesignation)){{ $contactDesignation }}<br>@endif
                    <span class="e-contact-org">EIDIKOS CERT.</span><br>
                    {{ $settings['office_address'] ?? '' }}
                    @if (filled($contactPhone))<br>Mobile: {{ $contactPhone }}@endif
                </div>
            </td>
            <td style="width:40%">
                <div class="e-note"><strong>Note:</strong> {{ $note }}</div>
            </td>
        </tr>
    </table>
</main>
</body>
</html>
