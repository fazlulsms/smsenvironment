<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.pdf_styles')
</head>
<body class="quotation-proposal proforma-document">
@php
    $currency = $settings['default_currency'] ?? 'BDT';
    $vatTreatment = $invoice->vat_treatment ?? 'exclusive';
    $taxNote = match ($vatTreatment) {
        'included' => 'Invoice amount is inclusive of applicable VAT.',
        'add' => (float) ($invoice->vat_amount ?? 0) > 0
            ? 'VAT has been shown separately according to the selected tax treatment.'
            : 'Applicable VAT may be added according to the selected tax treatment.',
        'not_applicable' => null,
        default => 'Invoice amount is exclusive of VAT. VAT/AIT or statutory deductions shall be treated according to applicable requirements.',
    };
    $footerBits = array_filter([
        $settings['office_address'] ?? null,
        $settings['phone'] ?? null,
        $settings['email'] ?? null,
        $settings['website'] ?? null,
    ]);
    $serviceRows = $invoice->items->map(function ($item) {
        $description = trim((string) $item->description);
        $service = $item->service;
        $title = $service?->short_name ?: $service?->name ?: $description ?: 'Service';
        $activities = collect($item->scope_items ?: [])->filter()->values();

        if ($activities->isEmpty() && str_contains(strtolower($description), ' - inclusive of ')) {
            [$titlePart, $scopePart] = explode(' - inclusive of ', $description, 2);
            $title = trim($titlePart) ?: $title;
            $activities = collect(preg_split('/,\s*|\s+and\s+/i', $scopePart))
                ->map(fn ($activity) => trim($activity, " \t\n\r\0\x0B."))
                ->filter()
                ->values();
        }

        return [
            'title' => $title,
            'activities' => $activities,
            'item' => $item,
        ];
    });
    $paymentTerms = collect(preg_split('/\r\n|\r|\n/', trim((string) $invoice->payment_terms)))
        ->map(fn ($line) => trim($line, " \t\n\r\0\x0B-*"))
        ->filter()
        ->values();
    if ($paymentTerms->isEmpty()) {
        $paymentTerms = collect([
            'Payment shall be made by bank transfer or account payee cheque.',
            'Where applicable, work will commence following confirmation of payment.',
            'VAT/AIT or statutory deductions shall be treated according to the stated invoice tax treatment and applicable requirements.',
        ]);
    }
    $bankRows = [
        'Beneficiary' => $bank['beneficiary_name'] ?? null,
        'Bank' => isset($bank['bank_name']) ? preg_replace('/\.+$/', '.', trim((string) $bank['bank_name'])) : null,
        'Branch' => $bank['branch'] ?? null,
        'Account No.' => $bank['account_number'] ?? null,
        'SWIFT' => $bank['swift_code'] ?? null,
    ];
@endphp

<div class="running-header">
    <table class="rh-table">
        <tr>
            <td class="rh-logo-cell">
                @if (!empty($settings['logo_path']) && file_exists(storage_path('app/public/'.$settings['logo_path'])))
                    <img class="rh-logo" src="{{ storage_path('app/public/'.$settings['logo_path']) }}" alt="">
                @endif
            </td>
            <td class="rh-brand-cell">
                <div class="rh-brand">{{ $settings['organization_name'] ?? 'SMS Environmental Alliance' }}</div>
                <div class="rh-tagline">{{ $settings['tagline'] ?? 'Environmental testing, assessment and compliance support' }}</div>
            </td>
            <td class="rh-title-cell">PROFORMA INVOICE</td>
        </tr>
    </table>
</div>

<div class="footer">
    <table class="footer-table">
        <tr>
            <td>SMS Environmental Alliance</td>
            <td class="text-center">Invoice: {{ $invoice->number }}</td>
            <td class="text-right">Page <span class="page-number"></span></td>
        </tr>
        <tr>
            <td colspan="3" class="footer-contact">
                {{ implode(' | ', $footerBits) }}@if (!empty($settings['pdf_note'])) | {{ $settings['pdf_note'] }} @endif
            </td>
        </tr>
    </table>
</div>

<section class="invoice-page">
    <table class="invoice-meta">
        <tr>
            <td><strong>Invoice No.</strong><br>{{ $invoice->number }}</td>
            <td class="text-right"><strong>Date</strong><br>{{ $invoice->date->format('d M Y') }}</td>
        </tr>
    </table>

    <table class="client-charge-table">
        <tr>
            <td>
                <div class="label">Bill To</div>
                @if(!empty($client['company_name']))<strong>{{ $client['company_name'] }}</strong><br>@endif
                @if(!empty($client['contact_person']))Attn: {{ $client['contact_person'] }}<br>@endif
                @if(!empty($client['designation'])){{ $client['designation'] }}<br>@endif
                @if(!empty($client['address'])){{ $client['address'] }}<br>@endif
                @if(!empty($client['email'])){{ $client['email'] }}@endif
            </td>
            <td>
                <div class="label">Charge For</div>
                <strong>{{ $invoice->charge_for ?: $serviceRows->pluck('title')->filter()->implode(', ') }}</strong>
            </td>
        </tr>
    </table>

    <div class="section financial-section">
        <table class="proposal-table invoice-service-table">
            <thead><tr><th style="width:6%">SL</th><th>Service / Description</th><th style="width:14%">Unit / Qty</th><th style="width:15%" class="text-right">Rate</th><th style="width:15%" class="text-right">Amount</th></tr></thead>
            <tbody>
            @foreach($serviceRows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        <div class="service-title">{{ $row['title'] }}</div>
                        @if($row['activities']->isNotEmpty())
                            <div class="scope-label">Including:</div>
                            <ul class="scope-list">
                                @foreach($row['activities'] as $activity)
                                    <li>{{ $activity }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="text-center">{{ $row['item']->unit }} / {{ number_format($row['item']->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($row['item']->unit_rate, 2) }}</td>
                    <td class="text-right">{{ number_format($row['item']->amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="financial-verification-table">
            <tr>
                <td class="financial-summary-cell">
                    @include('documents.pdf_totals', ['document' => $invoice, 'amountInWords' => $amountInWords])
                    @if($taxNote)
                        <div class="tax-note">{{ $taxNote }}</div>
                    @endif
                </td>
                <td class="verification-cell">
                    @if(!empty($verificationQr))
                        <div class="verification-block">
                            <h3>Invoice Verification</h3>
                            <img class="verification-qr" src="{{ $verificationQr }}" alt="Invoice verification QR code">
                            <div class="verification-caption">Scan to compare recorded details.</div>
                            <div class="verification-meta">Ref: {{ $invoice->number }}</div>
                            <div class="verification-meta">ID: {{ $invoice->verification_id }}</div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="invoice-lower-table">
        <tr>
            <td class="bank-cell">
                <h3>Bank Details</h3>
                <table class="bank-table invoice-bank-table">
                    @foreach($bankRows as $label => $value)
                        @if(filled($value))
                            <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
                        @endif
                    @endforeach
                </table>
            </td>
            <td class="terms-cell">
                <h3>Payment Terms</h3>
                <ul class="compact-list invoice-terms">
                    @foreach($paymentTerms->take(3) as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ul>
                <div class="prepared-by">
                    <h3>Prepared By</h3>
                    @if (!empty($settings['prepared_by_name']))<strong>{{ $settings['prepared_by_name'] }}</strong><br>@endif
                    @php $preparedDesignation = $settings['prepared_by_designation'] ?? 'Authorized Representative'; @endphp
                    {{ $preparedDesignation }}<br>
                    @if(trim((string) $preparedDesignation) !== 'SMS Environmental Alliance')
                        SMS Environmental Alliance
                    @endif
                </div>
            </td>
        </tr>
    </table>
</section>
</body>
</html>
