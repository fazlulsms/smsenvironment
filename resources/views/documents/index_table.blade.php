<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Number</th><th>Date</th><th>Client</th><th class="text-end">Amount</th><th>Created By</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td><a href="{{ $type === 'quotation' ? route('quotations.show', $document) : route('proforma-invoices.show', $document) }}">{{ $document->number }}</a></td>
                    <td>{{ $document->date->format('d M Y') }}</td>
                    <td>{{ $document->client?->company_name }}</td>
                    <td class="text-end">{{ number_format($document->total, 2) }}</td>
                    <td>{{ $document->creator?->name }}</td>
                    <td>{{ $document->updated_at?->format('d M Y') }}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-secondary" href="{{ $type === 'quotation' ? route('quotations.show', $document) : route('proforma-invoices.show', $document) }}">View</a>
                            <a class="btn btn-outline-secondary" href="{{ $type === 'quotation' ? route('quotations.edit', $document) : route('proforma-invoices.edit', $document) }}">Edit</a>
                            <a class="btn btn-outline-secondary" href="{{ $type === 'quotation' ? route('quotations.pdf', $document) : route('proforma-invoices.pdf', $document) }}">PDF</a>
                            <a class="btn btn-outline-secondary" href="{{ $type === 'quotation' ? route('quotations.email.create', $document) : route('proforma-invoices.email.create', $document) }}">Email</a>
                            <form method="post" action="{{ $type === 'quotation' ? route('quotations.duplicate', $document) : route('proforma-invoices.duplicate', $document) }}">
                                @csrf
                                <button class="btn btn-outline-secondary">Duplicate</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-secondary">No records yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
