<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.pdf_styles')
</head>
<body>
@include('documents.pdf_header', ['title' => 'QUOTATION', 'number' => $quotation->number, 'date' => $quotation->date])

<div class="row">
    <div class="col">
        <div class="label">Recipient</div>
        @if(!empty($client['contact_person'])){{ $client['contact_person'] }}<br>@endif
        @if(!empty($client['designation'])){{ $client['designation'] }}<br>@endif
        @if(!empty($client['company_name']))<strong>{{ $client['company_name'] }}</strong><br>@endif
        @if(!empty($client['address'])){{ $client['address'] }}<br>@endif
        @if(!empty($client['email'])){{ $client['email'] }}@endif
    </div>
    <div class="col">
        <div class="label">Subject</div>
        {{ $quotation->subject }}
    </div>
</div>

@if($quotation->intro_text)
    <p class="section">{{ $quotation->intro_text }}</p>
@endif

<div class="section">
    <div class="label">Financial Proposal</div>
    <table>
        <thead><tr><th style="width:7%">SL</th><th>Description / Particular</th><th style="width:15%">Unit / Qty</th><th style="width:16%" class="text-right">Unit Rate</th><th style="width:16%" class="text-right">Total</th></tr></thead>
        <tbody>
        @foreach($quotation->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->unit }} / {{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($item->unit_rate, 2) }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @include('documents.pdf_totals', ['document' => $quotation, 'amountInWords' => $amountInWords])
</div>

@if($quotation->compliance_note)
    <div class="section">
        <div class="label">Scope / Standards</div>
        {!! nl2br(e($quotation->compliance_note)) !!}
    </div>
@endif

@if($quotation->closing_text)
    <p class="section">{{ $quotation->closing_text }}</p>
@endif

@include('documents.pdf_bank', ['bank' => $bank])
@include('documents.pdf_terms', ['document' => $quotation, 'settings' => $settings])
</body>
</html>
