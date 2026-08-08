<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.pdf_styles')
</head>
<body>
@include('documents.pdf_header', ['title' => 'PROFORMA INVOICE', 'number' => $invoice->number, 'date' => $invoice->date])

<div class="row">
    <div class="col">
        <div class="label">Client Information</div>
        @if(!empty($client['company_name']))<strong>{{ $client['company_name'] }}</strong><br>@endif
        @if(!empty($client['contact_person'])){{ $client['contact_person'] }}<br>@endif
        @if(!empty($client['address'])){{ $client['address'] }}<br>@endif
        @if(!empty($client['email'])){{ $client['email'] }}@endif
    </div>
    <div class="col">
        <div class="label">Charge For</div>
        {{ $invoice->charge_for }}
    </div>
</div>

<div class="section">
    <div class="label">Service Type / Description</div>
    <table>
        <thead><tr><th style="width:7%">SL</th><th>Description</th><th style="width:15%">Unit / Qty</th><th style="width:16%" class="text-right">Rate</th><th style="width:16%" class="text-right">Amount</th></tr></thead>
        <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>@include('documents.item_description', ['item' => $item])</td>
                <td>{{ $item->unit }} / {{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">{{ number_format($item->unit_rate, 2) }}</td>
                <td class="text-right">{{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @include('documents.pdf_totals', ['document' => $invoice, 'amountInWords' => $amountInWords])
</div>

@include('documents.pdf_bank', ['bank' => $bank])
@include('documents.pdf_terms', ['document' => $invoice, 'settings' => $settings])
</body>
</html>
