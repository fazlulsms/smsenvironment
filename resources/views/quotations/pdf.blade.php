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
    $serviceRows = $quotation->items->map(function ($item) {
        $description = trim((string) $item->description);
        $service = $item->service;
        $title = $service?->short_name ?: $service?->name ?: $description ?: 'Service';
        $activities = collect($item->scope_items ?: [])->filter()->values();
        $commercialDescription = match (true) {
            str_contains(strtolower($title.' '.$description), 'environmental impact assessment') => 'Assessment of environmental aspects, risks and impacts associated with the facility activities and operations, including identification of significant environmental issues and applicable compliance requirements.',
            str_contains(strtolower($title.' '.$description), 'environmental management plan') => 'Development of a practical environmental management plan addressing identified environmental aspects, mitigation measures, monitoring requirements, responsibilities and continual improvement actions.',
            str_contains(strtolower($title.' '.$description), 'energy audit') => 'Assessment of energy consumption, major energy-using systems and operational practices to identify efficiency opportunities and practical energy-saving recommendations.',
            str_contains(strtolower($title.' '.$description), 'environmental and social impact') => 'Assessment of relevant environmental and social impacts, risks and mitigation requirements associated with the facility, project or operation.',
            str_contains(strtolower($title.' '.$description), 'parameter') => 'Assessment, monitoring and reporting of selected environmental parameters included in the agreed service package.',
            str_contains(strtolower($title.' '.$description), 'noise') => 'Measurement and assessment of applicable noise levels with reporting against relevant requirements.',
            str_contains(strtolower($title.' '.$description), 'test') => 'Testing, measurement and reporting for the selected environmental parameter or technical service.',
            default => $description !== '' && mb_strlen($description) <= 220 ? $description : 'Professional environmental assessment, testing, compliance or consultancy service according to the agreed scope.',
        };

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
            'description' => $commercialDescription,
            'activities' => $activities->isNotEmpty()
                && (($service?->service_type === \App\Models\Service::TYPE_BUNDLE) || str_contains(strtolower($title.' '.$description), 'parameter'))
                    ? $activities
                    : collect(),
            'item' => $item,
        ];
    });
    $scopeLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $quotation->scope_assessment)))->map(fn ($line) => trim($line))->filter()->values();
    $methodologyLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $quotation->methodology)))->map(fn ($line) => trim($line))->filter()->values();
    $deliverableLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $quotation->deliverables)))->map(fn ($line) => trim($line))->filter()->values();
    $responsibilityLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $quotation->client_responsibilities)))->map(fn ($line) => trim($line))->filter()->values();
    $approachText = $methodologyLines->implode(' ');
    $clientResponsibilitiesText = $responsibilityLines->implode(' ') ?: 'The client shall provide reasonable access to relevant premises, personnel, records, documents, operational information, utilities, sampling/monitoring locations and other resources reasonably required to complete the agreed assignment.';
    $terms = collect(preg_split('/\n\s*\n/', trim((string) $quotation->terms_conditions)))
        ->map(fn ($term) => trim($term))
        ->filter()
        ->values();
    if ($clientResponsibilitiesText !== '' && ! $terms->contains(fn ($term) => str_contains(strtolower($term), 'client responsibilit'))) {
        $terms = $terms->take(1)
            ->concat(['Client Responsibilities: '.$clientResponsibilitiesText])
            ->concat($terms->slice(1))
            ->values();
    }
    $termRows = $terms->chunk(2);
    $paymentLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $quotation->payment_terms)))->map(fn ($line) => trim($line))->filter()->values();
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
            <td class="rh-title-cell">QUOTATION</td>
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
    <h2>Service & Financial Proposal</h2>

    <div class="section financial-section">
        <table class="proposal-table">
            <thead><tr><th style="width:6%">SL</th><th>Service / Particular</th><th style="width:14%">Unit / Qty</th><th style="width:15%" class="text-right">Unit Rate</th><th style="width:15%" class="text-right">Amount</th></tr></thead>
            <tbody>
            @foreach($serviceRows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        <div class="service-title">{{ $row['title'] }}</div>
                        <div class="service-summary">{{ $row['description'] }}</div>
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
                    @include('documents.pdf_totals', ['document' => $quotation, 'amountInWords' => $amountInWords])
                </td>
                <td class="verification-cell">
                    @if(!empty($verificationQr))
                        <div class="verification-block">
                            <h3>Quotation Verification</h3>
                            <img class="verification-qr" src="{{ $verificationQr }}" alt="Quotation verification QR code">
                            <div class="verification-caption">Scan to compare recorded details.</div>
                            <div class="verification-meta">Ref: {{ $quotation->number }}</div>
                            <div class="verification-meta">ID: {{ $quotation->verification_id }}</div>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>


    @if($approachText !== '')
        <div class="section document-section">
            <h3>Assessment Approach</h3>
            <p>{{ $approachText }}</p>
        </div>
    @endif

    @if($deliverableLines->isNotEmpty())
        <div class="section document-section">
            <h3>Deliverables</h3>
            <ul class="compact-list">
                @foreach($deliverableLines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</section>

<section class="proposal-page terms-page">
    <h2>Commercial Terms & Conditions</h2>

    <div class="commercial-grid">
        <div class="commercial-block avoid-break">
            <h3>Tax Treatment</h3>
            @if ($taxNote)<p>{{ $taxNote }}</p>@endif
            @if ($quotation->ait_note)<p>{{ $quotation->ait_note }}</p>@endif
        </div>

        @if($paymentLines->isNotEmpty() || $quotation->validity_text || $quotation->notes)
            <div class="commercial-block avoid-break">
                <h3>Payment Terms</h3>
                @if($paymentLines->isNotEmpty())
                    <ol class="compact-ordered">
                        @foreach($paymentLines as $line)<li>{{ preg_replace('/^[A-Za-z ]+:\s*/', '', $line) }}</li>@endforeach
                    </ol>
                @endif
                @if ($quotation->validity_text)<p><strong>Proposal Validity:</strong> {{ $quotation->validity_text }}</p>@endif
                @if ($quotation->notes)<p>{!! nl2br(e($quotation->notes)) !!}</p>@endif
            </div>
        @endif
    </div>

    @include('documents.pdf_bank', ['bank' => $bank])

    @if($terms->isNotEmpty())
        <div class="terms-section">
            <table class="terms-table">
            @foreach($termRows as $termRow)
                <tr>
                @foreach($termRow as $term)
                    @php
                        $parts = str_contains($term, ':') ? explode(':', $term, 2) : [$term, ''];
                    @endphp
                    <td>
                        <strong>{{ ($loop->parent->iteration - 1) * 2 + $loop->iteration }}. {{ trim($parts[0]) }}</strong>
                        @if(!empty($parts[1]))
                            <div>{{ trim($parts[1]) }}</div>
                        @endif
                    </td>
                @endforeach
                @if($termRow->count() === 1)<td></td>@endif
                </tr>
            @endforeach
            </table>
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
