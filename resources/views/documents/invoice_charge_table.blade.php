@php
    // DESCRIPTION | AMOUNT commercial table. Renders from the saved snapshot:
    // Client Name / Site Name / Service / Charge For for the single-charge modes,
    // and SL / Description / Amount for itemized. No Unit/Qty/Rate.
    $mode = $invoice->charge_presentation ?? 'itemized';
    $first = $serviceRows->first();
    $firstItem = $first['item'] ?? null;
    $clientName = $invoice->client_snapshot['company_name'] ?? ($invoice->client?->company_name ?? '');
    $siteName = $invoice->site_name ?: $clientName;
    $serviceName = $invoice->charge_title ?: ($first['title'] ?? 'Environmental Services');
    $standards = collect($invoice->standards_snapshot['items'] ?? []);
    $standardsLabel = $invoice->standards_snapshot['category']['selection_label'] ?? 'Standards / Scope';
    $rowspan = $standards->isNotEmpty() ? 5 : 4;
@endphp

@if ($mode === 'itemized')
    <div class="ct-info">
        <div><span class="ct-label">Client Name:</span> {{ $clientName }}</div>
        <div><span class="ct-label">Site Name:</span> {{ $siteName }}</div>
        @if (filled($invoice->charge_title))<div><span class="ct-label">Service:</span> {{ $serviceName }}</div>@endif
    </div>
    <table class="commercial-table">
        <thead><tr><th style="width:6%">SL</th><th>Description</th><th class="ct-amount-col text-right">Amount ({{ $currency }})</th></tr></thead>
        <tbody>
            @foreach ($serviceRows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        <div class="service-title">{{ $row['title'] }}</div>
                        @if ($row['activities']->isNotEmpty())
                            <div class="ct-including">Including:</div>
                            <ul class="ct-list">@foreach ($row['activities'] as $activity)<li>{{ $activity }}</li>@endforeach</ul>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($row['item']->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <table class="commercial-table">
        <thead><tr><th>Description</th><th class="ct-amount-col text-right">Amount ({{ $currency }})</th></tr></thead>
        <tbody>
            <tr>
                <td><span class="ct-label">Client Name:</span> {{ $clientName }}</td>
                <td rowspan="{{ $rowspan }}" class="ct-amount">{{ number_format($firstItem?->amount ?? $invoice->subtotal, 2) }}</td>
            </tr>
            <tr><td><span class="ct-label">Site Name:</span> {{ $siteName }}</td></tr>
            <tr><td><span class="ct-label">Service:</span> {{ $serviceName }}</td></tr>
            @if ($standards->isNotEmpty())
                <tr>
                    <td>
                        <span class="ct-label">{{ $standardsLabel }}:</span>
                        <ul class="ct-list">@foreach ($standards as $s)<li>{{ $s['name'] }}</li>@endforeach</ul>
                    </td>
                </tr>
                <tr>
                    <td><span class="ct-label">Charge For:</span> {{ $firstItem?->description ?: $serviceName }}</td>
                </tr>
            @else
                <tr>
                    <td>
                        <span class="ct-label">Charge For:</span>
                        @if ($mode === 'consolidated')
                            {{ $firstItem?->description ?: $serviceName }}
                        @else
                            <div class="ct-including">Including:</div>
                            <ul class="ct-list">
                                @foreach (($first['activities'] ?? collect()) as $activity)<li>{{ $activity }}</li>@endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
@endif
