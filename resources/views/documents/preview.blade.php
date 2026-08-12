@php
    // Renders the saved document snapshot (never live service data), mirroring the
    // PDF's SERVICE → CHARGE FOR → AMOUNT hierarchy. No Unit/Qty/Rate.
    $isInvoice = $type === 'invoice';
    $mode = $document->charge_presentation ?? 'itemized';
    $currency = $document->settings_snapshot['default_currency'] ?? (\App\Models\Setting::current()->default_currency ?: 'BDT');
    $firstItem = $document->items->first();
    $serviceName = $document->charge_title ?: ($firstItem?->service?->short_name ?: ($firstItem?->description ?: 'Environmental Services'));
    $vatShown = ($document->vat_treatment ?? null) === 'add' && (float) ($document->vat_amount ?? 0) > 0 && ($document->show_vat_separately ?? true);
@endphp

<div class="card">
    <div class="card-body">
        <div class="row g-4 mb-3">
            <div class="col-md-6">
                <div class="muted-label">{{ $isInvoice ? 'Bill To' : 'Client' }}</div>
                <strong>{{ $document->client_snapshot['company_name'] ?? $document->client?->company_name }}</strong><br>
                @if (filled($document->client_snapshot['contact_person'] ?? $document->client?->contact_person))Attn: {{ $document->client_snapshot['contact_person'] ?? $document->client?->contact_person }}<br>@endif
                <span class="text-secondary">{{ $document->client_snapshot['address'] ?? $document->client?->address }}</span>
            </div>
            <div class="col-md-6">
                <div class="muted-label">Bank</div>
                {{ $document->bank_snapshot['bank_name'] ?? $document->bankAccount?->bank_name ?: 'Not selected' }}
            </div>
        </div>

        {{-- SERVICE / CHARGE FOR --}}
        <div class="mb-3">
            <div class="muted-label">{{ $isInvoice ? 'Service' : 'Subject' }}</div>
            <strong class="d-block mb-2" style="font-size:15px">{{ $isInvoice ? $serviceName : ($document->subject ?: '—') }}</strong>

            <div class="muted-label">Charge For</div>
            @if ($isInvoice && $mode === 'consolidated')
                <div>{{ $firstItem?->description ?: $serviceName }}</div>
            @elseif ($isInvoice && $mode === 'component_breakdown')
                <div class="cell-sub">Including:</div>
                <ul class="mb-0">
                    @foreach ($firstItem?->scope_items ?? [] as $scopeItem)<li>{{ $scopeItem }}</li>@endforeach
                </ul>
            @else
                <div class="table-responsive mt-1">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th style="width:6%">SL</th><th>Description</th><th class="num" style="width:22%">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($document->items as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div>{{ $item->service?->short_name ?: $item->description }}</div>
                                        @if ($item->service?->short_name && filled($item->description) && $item->description !== $item->service?->short_name)
                                            <div class="cell-sub">{{ $item->description }}</div>
                                        @endif
                                        @if (! empty($item->scope_items))
                                            <div class="cell-sub">Including:</div>
                                            <ul class="small mb-0">@foreach ($item->scope_items as $s)<li>{{ $s }}</li>@endforeach</ul>
                                        @endif
                                    </td>
                                    <td class="num money">{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Financial summary --}}
        <div class="d-flex justify-content-end">
            <table class="table table-sm w-auto mb-0" style="min-width:280px">
                <tr><td class="text-end text-secondary">Net Amount</td><td class="num" style="width:110px">{{ number_format($document->subtotal, 2) }}</td></tr>
                @if ((float) $document->adjustment !== 0.0)
                    <tr><td class="text-end text-secondary">Adjustment</td><td class="num">{{ number_format($document->adjustment, 2) }}</td></tr>
                @endif
                @if ($vatShown)
                    <tr><td class="text-end text-secondary">VAT @ {{ rtrim(rtrim(number_format((float) $document->vat_rate, 3), '0'), '.') }}%</td><td class="num">{{ number_format($document->vat_amount, 2) }}</td></tr>
                @endif
                <tr class="fw-bold" style="border-top:2px solid var(--brand)"><td class="text-end">Total Payable</td><td class="num money"><span class="cur">{{ $currency }}</span>{{ number_format($document->total, 2) }}</td></tr>
            </table>
        </div>
    </div>
</div>
