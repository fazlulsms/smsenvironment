@php $__isEidikos = ($type === 'invoice') && \App\Support\DocumentProfile::isEidikos($document->entity_code); @endphp
@if ($__isEidikos)
    @include('documents.eidikos_preview', ['document' => $document])
@else
@php
    // Detail/preview renders the saved snapshot with the same DESCRIPTION | AMOUNT
    // logic as the PDF. No Unit/Qty/Rate; never live service data.
    $isInvoice = $type === 'invoice';
    $mode = $document->charge_presentation ?? 'itemized';
    $currency = $document->settings_snapshot['default_currency'] ?? (\App\Models\Setting::current()->default_currency ?: 'BDT');
    $firstItem = $document->items->first();
    $clientName = $document->client_snapshot['company_name'] ?? $document->client?->company_name;
    $siteName = $isInvoice ? ($document->site_name ?: $clientName) : null;
    $serviceName = $document->charge_title ?: ($firstItem?->service?->short_name ?: ($firstItem?->description ?: 'Environmental Services'));
    $singleCharge = $isInvoice && ($mode === 'consolidated' || $mode === 'component_breakdown');
    $vatShown = ($document->vat_treatment ?? null) === 'add' && (float) ($document->vat_amount ?? 0) > 0 && ($document->show_vat_separately ?? true);
    $label = fn ($t) => '<span class="fw-bold" style="color:var(--brand)">'.$t.'</span>';
@endphp

<div class="card">
    <div class="card-body">
        <div class="row g-4 mb-3">
            <div class="col-md-6">
                <div class="muted-label">{{ $isInvoice ? 'Bill To' : 'Client' }}</div>
                <strong>{{ $clientName }}</strong><br>
                @if (filled($document->client_snapshot['contact_person'] ?? $document->client?->contact_person))Attn: {{ $document->client_snapshot['contact_person'] ?? $document->client?->contact_person }}<br>@endif
                <span class="text-secondary">{{ $document->client_snapshot['address'] ?? $document->client?->address }}</span>
            </div>
            <div class="col-md-6">
                <div class="muted-label">Bank</div>
                {{ $document->bank_snapshot['bank_name'] ?? $document->bankAccount?->bank_name ?: 'Not selected' }}
            </div>
        </div>

        @if ($singleCharge)
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Description</th><th class="num" style="width:26%">Amount ({{ $currency }})</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>{!! $label('Client Name:') !!} {{ $clientName }}</td>
                            <td class="num money" rowspan="4" style="vertical-align:middle">{{ number_format($firstItem?->amount ?? $document->subtotal, 2) }}</td>
                        </tr>
                        <tr><td>{!! $label('Site Name:') !!} {{ $siteName }}</td></tr>
                        <tr><td>{!! $label('Service:') !!} {{ $serviceName }}</td></tr>
                        <tr>
                            <td>
                                {!! $label('Charge For:') !!}
                                @if ($mode === 'consolidated')
                                    {{ $firstItem?->description ?: $serviceName }}
                                @else
                                    <div class="cell-sub mt-1">Including:</div>
                                    <ul class="mb-0">@foreach ($firstItem?->scope_items ?? [] as $s)<li>{{ $s }}</li>@endforeach</ul>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            @if ($isInvoice)
                <div class="mb-2 small">
                    <div>{!! $label('Client Name:') !!} {{ $clientName }}</div>
                    <div>{!! $label('Site Name:') !!} {{ $siteName }}</div>
                    @if (filled($document->charge_title))<div>{!! $label('Service:') !!} {{ $serviceName }}</div>@endif
                </div>
            @else
                <div class="mb-2"><span class="muted-label">Subject</span><br><strong>{{ $document->subject ?: '—' }}</strong></div>
            @endif
            <div class="table-responsive">
                <table class="table align-middle mb-0">
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

        <div class="d-flex justify-content-end mt-3">
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
@endif
