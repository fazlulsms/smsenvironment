<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.pdf_styles')
</head>
<body class="quotation-proposal">
@php
    $currency = $settings['default_currency'] ?? 'BDT';
    $netPayableBeforeVat = (float) $quotation->subtotal + (float) $quotation->adjustment;
    $vatTreatment = $quotation->vat_treatment ?? 'exclusive';
    $storedVatNote = trim((string) $quotation->vat_note);
    if ($vatTreatment === 'add' && str_contains(strtolower($storedVatNote), 'exclusive of vat')) {
        $storedVatNote = '';
    }
    $taxNote = $storedVatNote ?: match ($vatTreatment) {
        'included' => 'Quoted fees are inclusive of applicable VAT.',
        'add' => (float) $quotation->vat_amount > 0
            ? 'VAT has been shown separately according to the selected quotation tax treatment.'
            : 'Applicable VAT may be added according to the selected quotation tax treatment.',
        'not_applicable' => null,
        default => 'Quoted fees are exclusive of VAT. Applicable VAT shall be added or borne as required under prevailing regulations.',
    };
    $footerBits = array_filter([
        $settings['office_address'] ?? null,
        $settings['phone'] ?? null,
        $settings['email'] ?? null,
        $settings['website'] ?? null,
    ]);
    $scopeRows = $quotation->items->map(function ($item) {
        $description = trim((string) $item->description);
        $title = $description ?: 'Service';
        $activities = collect($item->scope_items ?: [])->filter()->values();

        if ($activities->isEmpty() && str_contains(strtolower($description), 'environmental management plan')) {
            $title = 'Environmental Management Plan (EMP)';
            $activities = collect([
                'Document review',
                'Onsite assessment',
                'Relevant data collection',
                'Data analysis',
                'Identification of environmental aspects and risks',
                'Development of mitigation measures',
                'Development of monitoring requirements',
                'Final report preparation',
            ]);
        }

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
    $terms = collect(preg_split('/\n\s*\n/', trim((string) $quotation->terms_conditions)))
        ->map(fn ($term) => trim($term))
        ->filter()
        ->values();
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
                <div class="rh-tagline">{{ $settings['tagline'] ?? 'Environmental Testing, Assessment & Compliance Services' }}</div>
            </td>
        </tr>
    </table>
</div>
<div class="footer">
    <table class="footer-table">
        <tr>
            <td>SMS Environmental Alliance</td>
            <td class="text-center">Quotation: {{ $quotation->number }}</td>
            <td class="text-right">Page <span class="page-number"></span></td>
        </tr>
        <tr>
            <td colspan="3" class="footer-contact">
                {{ implode(' | ', $footerBits) }}@if (!empty($settings['pdf_note'])) | {{ $settings['pdf_note'] }} @endif
            </td>
        </tr>
    </table>
</div>

<section class="proposal-page letter-page">
    <div class="document-kicker">Commercial Proposal / Quotation</div>
    <h1>Quotation</h1>

    <table class="letter-meta">
        <tr>
            <td><strong>Reference No.</strong><br>{{ $quotation->number }}</td>
            <td class="text-right"><strong>Date</strong><br>{{ $quotation->date->format('d M Y') }}</td>
        </tr>
    </table>

    <div class="letter-recipient">
        <div class="label">To</div>
        @if(!empty($client['contact_person'])){{ $client['contact_person'] }}<br>@endif
        @if(!empty($client['designation'])){{ $client['designation'] }}<br>@endif
        @if(!empty($client['company_name']))<strong>{{ $client['company_name'] }}</strong><br>@endif
        @if(!empty($client['address'])){{ $client['address'] }}<br>@endif
        @if(!empty($client['email'])){{ $client['email'] }}@endif
    </div>

    <div class="subject-box">
        <div class="label">Subject</div>
        <strong>{{ $quotation->subject ?: 'Quotation for Environmental Services' }}</strong>
    </div>

    <p>Dear {{ !empty($client['contact_person']) ? $client['contact_person'] : 'Sir/Madam' }},</p>

    @foreach (preg_split('/\n\s*\n/', trim((string) $quotation->intro_text)) as $paragraph)
        @if(trim($paragraph) !== '')
            <p>{{ trim($paragraph) }}</p>
        @endif
    @endforeach

    @if($quotation->compliance_note)
        <div class="section avoid-break">
            <div class="label">Applicable Reference Framework</div>
            <p>The assessment/reporting, where applicable, will consider relevant requirements and guidance including:</p>
            <ul class="compact-list">
                @foreach(preg_split('/\r\n|\r|\n/', $quotation->compliance_note) as $line)
                    @if(trim($line) !== '')
                        <li>{{ trim($line, " \t\n\r\0\x0B-•") }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    @if($quotation->closing_text)
        <p>{{ $quotation->closing_text }}</p>
    @else
        <p>We look forward to supporting your environmental compliance requirements and remain available for any clarification required.</p>
    @endif

    <div class="signature-block">
        <div>Sincerely,</div>
        <div class="signature-space"></div>
        @if (!empty($settings['prepared_by_name']))<strong>{{ $settings['prepared_by_name'] }}</strong><br>@endif
        @if (!empty($settings['prepared_by_designation'])){{ $settings['prepared_by_designation'] }}<br>@endif
        @if (!empty($settings['phone'])){{ $settings['phone'] }} @endif
        @if (!empty($settings['email'])){{ !empty($settings['phone']) ? ' | ' : '' }}{{ $settings['email'] }}@endif
    </div>
</section>

<section class="proposal-page content-page">
    <h2>Scope & Financial Proposal</h2>

    <div class="section scope-section">
        <h3>Scope of Service / Scope & Deliverables</h3>
        @foreach($scopeRows as $row)
            <div class="scope-card avoid-break">
                <div class="service-title">{{ $row['title'] }}</div>
                @if($row['activities']->isNotEmpty())
                    <div class="scope-label">Scope / Included Activities:</div>
                    <ul class="scope-list">
                        @foreach($row['activities'] as $activity)
                            <li>{{ $activity }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    <div class="section financial-section">
        <h3>Financial Proposal</h3>

        <table class="proposal-table">
            <thead><tr><th style="width:7%">SL</th><th>Service</th><th style="width:15%">Unit / Qty</th><th style="width:16%" class="text-right">Unit Rate</th><th style="width:16%" class="text-right">Amount</th></tr></thead>
            <tbody>
            @foreach($scopeRows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td class="text-center">{{ $row['item']->unit }} / {{ number_format($row['item']->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($row['item']->unit_rate, 2) }}</td>
                    <td class="text-right">{{ number_format($row['item']->amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @include('documents.pdf_totals', ['document' => $quotation, 'amountInWords' => $amountInWords])
    </div>

    <div class="section avoid-break">
        <h3>Tax Treatment</h3>
        @if ($taxNote)<p>{{ $taxNote }}</p>@endif
        @if ($quotation->ait_note)<p>{{ $quotation->ait_note }}</p>@endif
    </div>

    @if ($quotation->payment_terms || $quotation->validity_text || $quotation->notes)
        <div class="section avoid-break">
            <h3>Payment Terms</h3>
            @if ($quotation->payment_terms)<div>{!! nl2br(e($quotation->payment_terms)) !!}</div>@endif
            @if ($quotation->validity_text)<p><strong>Validity:</strong> {{ $quotation->validity_text }}</p>@endif
            @if ($quotation->notes)<p>{!! nl2br(e($quotation->notes)) !!}</p>@endif
        </div>
    @endif

    @include('documents.pdf_bank', ['bank' => $bank])
</section>

<section class="proposal-page terms-page">
    @if($terms->isNotEmpty())
        <div class="terms-section">
            <h2>Terms & Conditions</h2>
            <ol class="terms-list">
            @foreach($terms as $term)
                @php
                    $parts = str_contains($term, ':') ? explode(':', $term, 2) : [$term, ''];
                @endphp
                <li>
                    <strong>{{ trim($parts[0]) }}</strong>
                    @if(!empty($parts[1]))
                        <div>{{ trim($parts[1]) }}</div>
                    @endif
                </li>
            @endforeach
            </ol>
        </div>
    @endif

    @if($quotation->include_acceptance)
        <div class="acceptance-block">
            <h2>Acceptance of Quotation</h2>
            <p>{{ $quotation->acceptance_text ?: 'We confirm acceptance of the scope, commercial terms and conditions stated in this quotation and authorize SMS Environmental Alliance to proceed.' }}</p>
            <table class="acceptance-table">
                <tr><td>Client Company:</td><td></td><td>Authorized Name:</td><td></td></tr>
                <tr><td>Designation:</td><td></td><td>Signature:</td><td></td></tr>
                <tr><td>Date:</td><td></td><td>Company Seal:</td><td></td></tr>
            </table>
            <div class="acceptance-ref"><strong>Quotation Reference:</strong> {{ $quotation->number }}</div>
        </div>
    @endif
</section>
</body>
</html>
