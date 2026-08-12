@php
    // Renders the saved document snapshot (never live service data), using the
    // same charge-presentation model as the PDF. Unit/Qty/Rate are not shown.
    $isInvoice = $type === 'invoice';
    $mode = $document->charge_presentation ?? 'itemized';
    $currency = $document->settings_snapshot['default_currency'] ?? (\App\Models\Setting::current()->default_currency ?: 'BDT');
    $chargeFor = $isInvoice
        ? ($document->charge_title ?: $document->charge_for)
        : $document->subject;
    $firstItem = $document->items->first();
    $title = $document->charge_title ?: ($firstItem?->service?->short_name ?: $firstItem?->description);
    $twoCol = $isInvoice && ($mode === 'consolidated' || $mode === 'component_breakdown');
    $labelSpan = $twoCol ? 1 : 2;
@endphp

<div class="card">
    <div class="card-body">
        <div class="row g-4 mb-3">
            <div class="col-md-6">
                <div class="muted-label">Client</div>
                <strong>{{ $document->client_snapshot['company_name'] ?? $document->client?->company_name }}</strong><br>
                {{ $document->client_snapshot['contact_person'] ?? $document->client?->contact_person }}<br>
                <span class="text-secondary">{{ $document->client_snapshot['address'] ?? $document->client?->address }}</span>
            </div>
            <div class="col-md-6">
                <div class="muted-label">{{ $isInvoice ? 'Charge For' : 'Subject' }}</div>
                <strong>{{ $chargeFor ?: '—' }}</strong>
                <div class="muted-label mt-3">Bank</div>
                {{ $document->bank_snapshot['bank_name'] ?? $document->bankAccount?->bank_name ?: 'Not selected' }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                @if ($isInvoice && ($mode === 'consolidated' || $mode === 'component_breakdown'))
                    <thead><tr><th>Charge For</th><th class="num" style="width:26%">Amount</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $title ?: 'Service' }}</div>
                                @if ($mode === 'consolidated' && filled($firstItem?->description))
                                    <div class="cell-sub mt-1">{{ $firstItem->description }}</div>
                                @endif
                                @if ($mode === 'component_breakdown' && ! empty($firstItem?->scope_items))
                                    <div class="cell-sub mt-1">Including:</div>
                                    <ul class="small mb-0">
                                        @foreach ($firstItem->scope_items as $scopeItem)<li>{{ $scopeItem }}</li>@endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="num money">{{ number_format($firstItem?->amount ?? $document->subtotal, 2) }}</td>
                        </tr>
                    </tbody>
                @else
                    <thead><tr><th style="width:6%">SL</th><th>Service / Particular</th><th class="num" style="width:22%">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($document->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->service?->short_name ?: $item->description }}</div>
                                    @if ($item->service?->short_name && filled($item->description) && $item->description !== $item->service?->short_name)
                                        <div class="cell-sub mt-1">{{ $item->description }}</div>
                                    @endif
                                    @if (! empty($item->scope_items))
                                        <div class="cell-sub mt-1">Including:</div>
                                        <ul class="small mb-0">
                                            @foreach ($item->scope_items as $scopeItem)<li>{{ $scopeItem }}</li>@endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="num money">{{ number_format($item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
                <tfoot>
                    <tr><th class="text-end" colspan="{{ $labelSpan }}">Net Amount</th><th class="num">{{ number_format($document->subtotal, 2) }}</th></tr>
                    @if ((float) $document->adjustment !== 0.0)
                        <tr><th class="text-end" colspan="{{ $labelSpan }}">Adjustment</th><th class="num">{{ number_format($document->adjustment, 2) }}</th></tr>
                    @endif
                    @if (($document->vat_treatment ?? null) === 'add' && (float) ($document->vat_amount ?? 0) > 0 && ($document->show_vat_separately ?? true))
                        <tr><th class="text-end" colspan="{{ $labelSpan }}">VAT @ {{ rtrim(rtrim(number_format((float) $document->vat_rate, 3), '0'), '.') }}%</th><th class="num">{{ number_format($document->vat_amount, 2) }}</th></tr>
                    @endif
                    <tr><th class="text-end" colspan="{{ $labelSpan }}">Total Payable</th><th class="num money"><span class="cur">{{ $currency }}</span>{{ number_format($document->total, 2) }}</th></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
