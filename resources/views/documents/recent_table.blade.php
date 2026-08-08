<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th>Number</th>
            <th>Client</th>
            <th class="text-end">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($documents as $document)
            <tr>
                <td>
                    <a href="{{ $type === 'quotation' ? route('quotations.show', $document) : route('proforma-invoices.show', $document) }}">
                        {{ $document->number }}
                    </a>
                    <div class="text-secondary small">{{ $document->date?->format('d M Y') }}</div>
                </td>
                <td>{{ $document->client?->company_name }}</td>
                <td class="text-end">{{ number_format($document->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-secondary">No records yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
