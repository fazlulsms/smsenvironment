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
    if ($paymentTerms->isEmpty() || $paymentTerms->count() < 4) {
        $paymentTerms = collect([
            'Payment shall be made by account payee cheque or bank transfer in favour of SMS Environmental Alliance.',
            'Where advance payment is applicable, the assignment will commence or be scheduled following confirmation of the applicable payment.',
            'VAT, AIT/withholding tax and other statutory deductions, where applicable, shall be treated according to the stated invoice tax treatment and prevailing statutory requirements.',
            'The client is requested to mention the Proforma Invoice reference when making payment or sending payment confirmation.',
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
            <td>{{ $settings['pdf_note'] ?? 'This is a computer-generated document and does not require a physical signature.' }}</td>
            <td class="text-center">Invoice: {{ $invoice->number }}</td>
            <td class="text-right">Page <span class="page-number"></span></td>
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
        @include('documents.invoice_charge_table', ['invoice' => $invoice, 'serviceRows' => $serviceRows, 'currency' => $currency])

        <table class="invoice-financial-summary">
            <tr><td>Net Amount</td><td class="text-right">{{ number_format($invoice->subtotal, 2) }}</td></tr>
            @if ((float) $invoice->adjustment !== 0.0)
                <tr><td>Discount / Adjustment</td><td class="text-right">{{ number_format($invoice->adjustment, 2) }}</td></tr>
            @endif
            @if (($invoice->vat_treatment ?? null) === 'add' && (float) ($invoice->vat_amount ?? 0) > 0 && ($invoice->show_vat_separately ?? true))
                <tr><td>VAT @ {{ rtrim(rtrim(number_format((float) $invoice->vat_rate, 3), '0'), '.') }}%</td><td class="text-right">{{ number_format($invoice->vat_amount, 2) }}</td></tr>
            @endif
            <tr class="grand"><td>Total Payable Amount</td><td class="text-right">{{ number_format($invoice->total, 2) }}</td></tr>
        </table>
        <div class="invoice-amount-words"><strong>Amount in Words:</strong> {{ $amountInWords }}</div>
        @if($taxNote)
            <div class="tax-note">{{ $taxNote }}</div>
        @endif
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
                <ol class="invoice-terms">
                    @foreach($paymentTerms->take(4) as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ol>
            </td>
        </tr>
    </table>

    <div class="prepared-section">
        <h3>Prepared By</h3>
        @if (!empty($settings['prepared_by_name']))
            <strong>{{ $settings['prepared_by_name'] }}</strong><br>
        @endif
        @php $preparedDesignation = $settings['prepared_by_designation'] ?? 'Authorized Representative'; @endphp
        {{ $preparedDesignation }}<br>
        @if(trim((string) $preparedDesignation) !== 'SMS Environmental Alliance')
            SMS Environmental Alliance<br>
        @endif
        <div class="authorization-note">Electronically generated and authorized through the SMSEA Office system.</div>
        <div class="signature-line"></div>
        <div class="signature-caption">Authorized Signature</div>
    </div>

    @if(!empty($verificationQr))
        <table class="invoice-verification-strip">
            <tr>
                <td class="verification-strip-qr-cell">
                    <img class="verification-strip-qr" src="{{ $verificationQr }}" alt="Invoice verification QR code">
                </td>
                <td class="verification-strip-details">
                    <h3>Invoice Verification</h3>
                    <div>Scan to compare recorded details.</div>
                    <div class="verification-strip-meta">Ref: {{ $invoice->number }}</div>
                    <div class="verification-strip-meta">ID: {{ $invoice->verification_id }}</div>
                </td>
                <td class="verification-strip-contact">
                    <strong>SMS Environmental Alliance</strong><br>
                    {{ implode(' | ', $footerBits) }}
                </td>
            </tr>
        </table>
    @endif
</section>
</body>
</html>
