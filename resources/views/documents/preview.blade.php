<div class="panel p-4">
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="muted-label">Client</div>
            <strong>{{ $document->client->company_name }}</strong><br>
            {{ $document->client->contact_person }}<br>
            <span class="text-secondary">{{ $document->client->address }}</span>
        </div>
        <div class="col-md-6">
            <div class="muted-label">{{ $type === 'quotation' ? 'Subject' : 'Charge For' }}</div>
            {{ $type === 'quotation' ? $document->subject : $document->charge_for }}
            <div class="muted-label mt-3">Bank</div>
            {{ $document->bankAccount?->bank_name ?: 'Not selected' }}
        </div>
    </div>
    <table class="table">
        <thead><tr><th>SL</th><th>Description</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        @foreach ($document->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->unit }}</td>
                <td class="text-end">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-end">{{ number_format($item->unit_rate, 2) }}</td>
                <td class="text-end">{{ number_format($item->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr><th colspan="5" class="text-end">Subtotal</th><th class="text-end">{{ number_format($document->subtotal, 2) }}</th></tr>
        <tr><th colspan="5" class="text-end">Adjustment</th><th class="text-end">{{ number_format($document->adjustment, 2) }}</th></tr>
        <tr><th colspan="5" class="text-end">Total</th><th class="text-end">{{ number_format($document->total, 2) }}</th></tr>
        </tfoot>
    </table>
</div>
