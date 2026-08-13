@php
    // Eidikos in-app detail: mirrors the PDF business hierarchy (reference / client
    // / description / amount / currency conversion / payment details) in the app UI.
    $settings = $document->settings_snapshot ?: [];
    $m = \App\Support\InvoiceMoney::context($document, $settings);
    $fmt = fn ($a) => $m['currency'].' '.number_format((float) $a, 2);
    $fmtBase = fn ($a) => $m['base'].' '.number_format((float) $a, 2);
    $blue = '#1d4ed8';
    $clientName = $document->client_snapshot['company_name'] ?? $document->client?->company_name;
    $siteName = $document->site_name ?: $clientName;
    $mode = $document->charge_presentation ?? 'itemized';
    $items = $document->items;
    $firstItem = $items->first();
    $serviceName = $document->charge_title ?: ($firstItem?->service?->short_name ?: ($firstItem?->description ?: 'Service'));
    $singleCharge = in_array($mode, ['consolidated', 'component_breakdown'], true);
    $vatShown = ($document->vat_treatment ?? null) === 'add' && (float) ($document->vat_amount ?? 0) > 0 && ($document->show_vat_separately ?? true);
    $bank = $document->bank_snapshot ?: [];
    $bankRows = array_filter([
        'Beneficiary' => $bank['beneficiary_name'] ?? null,
        'Bank' => $bank['bank_name'] ?? null,
        'Branch' => $bank['branch'] ?? null,
        'Account No.' => $bank['account_number'] ?? null,
        'SWIFT' => $bank['swift_code'] ?? null,
    ], fn ($v) => filled($v));
    $terms = collect(preg_split('/\r\n|\r|\n/', (string) ($settings['invoice_payment_terms'] ?? '')))
        ->map(fn ($l) => trim($l, " \t\n\r\0\x0B-*•"))->filter()->values();
    $words = app(\App\Services\AmountInWords::class)->convert(
        $m['words_amount'], $m['words_currency'],
        $settings['currency_major_name'] ?? 'Taka', $settings['currency_minor_name'] ?? 'Paisa'
    );
    $bdtWords = $m['dual']
        ? app(\App\Services\AmountInWords::class)->convert($m['base_words_amount'], $m['base'], 'Taka', 'Paisa')
        : null;
    $label = fn ($t) => '<span class="fw-bold" style="color:'.$blue.'">'.$t.'</span>';
@endphp

<div class="card">
    <div class="card-body">
        <div class="d-inline-block px-2 py-1 mb-3" style="background:{{ $blue }};color:#fff;font-weight:700;letter-spacing:2px;border-radius:4px">EIDIKOS CERT. · PROFORMA INVOICE</div>

        <div class="row g-4 mb-3">
            <div class="col-md-6">
                <div class="muted-label" style="color:{{ $blue }}">Document</div>
                <table class="table table-sm mb-0">
                    <tr><td class="text-secondary" style="width:45%">Reference No.</td><td>{{ $document->reference_no ?: '—' }}</td></tr>
                    <tr><td class="text-secondary">Invoice Ref. Number</td><td>{{ $document->number }}</td></tr>
                    <tr><td class="text-secondary">Date</td><td>{{ $document->date->format('d M Y') }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="muted-label" style="color:{{ $blue }}">Client</div>
                <table class="table table-sm mb-0">
                    <tr><td class="text-secondary" style="width:35%">Client Name</td><td>{{ $clientName }}</td></tr>
                    <tr><td class="text-secondary">Site Name</td><td>{{ $siteName }}</td></tr>
                    @if (filled($document->client_snapshot['address'] ?? $document->client?->address))<tr><td class="text-secondary">Site Address</td><td>{{ $document->client_snapshot['address'] ?? $document->client?->address }}</td></tr>@endif
                    @if (filled($document->client_snapshot['contact_person'] ?? $document->client?->contact_person))<tr><td class="text-secondary">Attention</td><td>{{ $document->client_snapshot['contact_person'] ?? $document->client?->contact_person }}</td></tr>@endif
                    @if (filled($document->client_snapshot['email'] ?? $document->client?->email))<tr><td class="text-secondary">Email</td><td>{{ $document->client_snapshot['email'] ?? $document->client?->email }}</td></tr>@endif
                </table>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr style="background:{{ $blue }};color:#fff"><th style="width:8%">S/N</th><th>Description of Particular</th><th class="num" style="width:24%">Amount ({{ $m['currency'] }})</th></tr></thead>
                <tbody>
                    @if ($singleCharge)
                        <tr>
                            <td>1</td>
                            <td>
                                <div class="fw-bold">{{ $serviceName }}</div>
                                @if ($mode === 'consolidated')
                                    @if (filled($firstItem?->description) && $firstItem->description !== $serviceName)<div class="cell-sub">{{ $firstItem->description }}</div>@endif
                                @else
                                    <div class="cell-sub mt-1">Including:</div>
                                    <ul class="small mb-0">@foreach ($firstItem?->scope_items ?? [] as $s)<li>{{ $s }}</li>@endforeach</ul>
                                @endif
                            </td>
                            <td class="num money">{{ $fmt($firstItem?->amount ?? $m['subtotal']) }}</td>
                        </tr>
                    @else
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-bold">{{ $item->service?->short_name ?: $item->description }}</div>
                                    @if ($item->service?->short_name && filled($item->description) && $item->description !== $item->service?->short_name)<div class="cell-sub">{{ $item->description }}</div>@endif
                                    @if (! empty($item->scope_items))<div class="cell-sub">Including:</div><ul class="small mb-0">@foreach ($item->scope_items as $s)<li>{{ $s }}</li>@endforeach</ul>@endif
                                </td>
                                <td class="num money">{{ $fmt($item->amount) }}</td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <table class="table table-sm w-auto mb-0" style="min-width:320px">
                <tr><td class="text-end text-secondary">Net Amount ({{ $m['currency'] }})</td><td class="num" style="width:150px">{{ $fmt($m['subtotal']) }}</td></tr>
                @if ((float) $document->adjustment !== 0.0)
                    <tr><td class="text-end text-secondary">Adjustment</td><td class="num">{{ $fmt($document->adjustment) }}</td></tr>
                @endif
                @if ($vatShown)
                    <tr><td class="text-end text-secondary">VAT @ {{ rtrim(rtrim(number_format((float) $document->vat_rate, 3), '0'), '.') }}%</td><td class="num">{{ $fmt($m['vat']) }}</td></tr>
                @endif
                <tr class="fw-bold" style="border-top:2px solid {{ $blue }};color:{{ $blue }}"><td class="text-end">Total Payable ({{ $m['currency'] }})</td><td class="num money">{{ $fmt($m['total']) }}</td></tr>
                @if ($m['dual'])
                    <tr><td class="text-end text-secondary">Net Amount ({{ $m['base'] }})</td><td class="num">{{ $fmtBase($m['base_subtotal']) }}</td></tr>
                    <tr class="fw-bold" style="color:#15803d"><td class="text-end">Total Payable ({{ $m['base'] }})</td><td class="num money">{{ $fmtBase($m['base_total']) }}</td></tr>
                    <tr><td colspan="2" class="text-end small text-secondary">Conversion Rate: 1 {{ $m['currency'] }} = {{ $m['base'] }} {{ number_format($m['rate'], 2) }}</td></tr>
                @endif
            </table>
        </div>

        <div class="mt-2"><strong>In Words:</strong> {{ $words }}</div>
        @if ($bdtWords)<div class="small text-secondary"><strong>{{ $m['base'] }} Equivalent in Words:</strong> {{ $bdtWords }}</div>@endif

        <div class="mt-4 row g-4">
            <div class="col-md-6">
                <div class="muted-label" style="color:{{ $blue }}">Payment Details · Bank</div>
                <table class="table table-sm mb-0">
                    @foreach ($bankRows as $k => $v)<tr><td class="text-secondary" style="width:38%">{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach
                </table>
            </div>
            <div class="col-md-6">
                <div class="muted-label" style="color:{{ $blue }}">Terms &amp; Conditions</div>
                @if ($terms->isNotEmpty())
                    <ul class="small mb-0">@foreach ($terms as $t)<li>{{ $t }}</li>@endforeach</ul>
                @else
                    <div class="text-secondary small">Entity default terms apply.</div>
                @endif
            </div>
        </div>
    </div>
</div>
