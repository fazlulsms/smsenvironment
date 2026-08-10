@php
    $currency = \App\Models\Setting::current()->default_currency ?: 'BDT';
    $isQuote = $type === 'quotation';
    $showRoute = fn ($d) => $isQuote ? route('quotations.show', $d) : route('proforma-invoices.show', $d);
    $editRoute = fn ($d) => $isQuote ? route('quotations.edit', $d) : route('proforma-invoices.edit', $d);
    $pdfRoute = fn ($d) => $isQuote ? route('quotations.pdf', $d) : route('proforma-invoices.pdf', $d);
    $emailRoute = fn ($d) => $isQuote ? route('quotations.email.create', $d) : route('proforma-invoices.email.create', $d);
    $dupRoute = fn ($d) => $isQuote ? route('quotations.duplicate', $d) : route('proforma-invoices.duplicate', $d);
    $serviceLabel = function ($doc) {
        $names = $doc->items->map(fn ($i) => $i->service?->short_name ?: $i->service?->name ?: $i->description)->filter()->unique()->values();
        if ($names->isEmpty()) return '—';
        return $names->count() === 1 ? $names->first() : $names->first().' +'.($names->count() - 1);
    };
@endphp

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ $isQuote ? 'Reference' : 'Invoice No.' }}</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th class="num">{{ $isQuote ? 'Amount' : 'Total' }}</th>
                    <th>Email</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($documents as $document)
                <tr>
                    <td>
                        <a class="row-title" href="{{ $showRoute($document) }}">{{ $document->number }}</a>
                        <div class="cell-sub">by {{ $document->creator?->name ?? '—' }}</div>
                    </td>
                    <td>{{ $document->client?->company_name ?? ($document->client_snapshot['company_name'] ?? '—') }}</td>
                    <td class="cell-sub">{{ $serviceLabel($document) }}</td>
                    <td class="cell-sub">{{ $document->date?->format('d M Y') }}</td>
                    <td class="num money"><span class="cur">{{ $currency }}</span>{{ number_format($document->total, 2) }}</td>
                    <td><x-email-status :deliveries="$document->emailDeliveries" /></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ $showRoute($document) }}" title="View"><x-icon name="eye" :size="16" /></a>
                            <a class="btn-icon" href="{{ $pdfRoute($document) }}" title="Download PDF"><x-icon name="download" :size="16" /></a>
                            <a class="btn-icon" href="{{ $emailRoute($document) }}" title="Send email"><x-icon name="send" :size="16" /></a>
                            <div class="dropdown">
                                <button class="btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More"><x-icon name="dots" :size="16" /></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item" href="{{ $editRoute($document) }}"><x-icon name="edit" :size="15" class="me-2" />Edit</a></li>
                                    <li>
                                        <form method="post" action="{{ $dupRoute($document) }}">
                                            @csrf
                                            <button class="dropdown-item" type="submit"><x-icon name="copy" :size="15" class="me-2" />Duplicate</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">
                    <x-empty-state :icon="$isQuote ? 'quotation' : 'invoice'"
                        title="{{ $isQuote ? 'No quotations yet' : 'No proforma invoices yet' }}"
                        message="{{ $isQuote ? 'Create your first quotation to get started.' : 'Create your first proforma invoice to get started.' }}">
                        <a class="btn btn-primary btn-sm" href="{{ $isQuote ? route('quotations.create') : route('proforma-invoices.create') }}">
                            <x-icon name="plus" :size="15" /> {{ $isQuote ? 'Create Quotation' : 'Create Invoice' }}
                        </a>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($documents->hasPages())
    <div class="mt-3">{{ $documents->links() }}</div>
@endif
