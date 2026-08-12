@php
    // Renders only the charge/service table. Header, totals, bank, QR and footer
    // are shared by the invoice template regardless of presentation mode.
    $mode = $invoice->charge_presentation ?? 'itemized';
@endphp

@if ($mode === 'consolidated' || $mode === 'component_breakdown')
    @php
        $row = $serviceRows->first();
        $item = $row['item'] ?? null;
        $title = $invoice->charge_title ?: ($row['title'] ?? 'Service');
        $amount = $item ? (float) $item->amount : (float) $invoice->subtotal;
    @endphp
    <table class="proposal-table invoice-service-table invoice-charge-2col">
        <thead><tr><th>Charge For</th><th style="width:28%" class="text-right">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td>
                    <div class="service-title">{{ $title }}</div>
                    @if ($mode === 'consolidated' && $item && filled($item->description))
                        <div class="charge-desc">{{ $item->description }}</div>
                    @endif
                    @if ($mode === 'component_breakdown' && $row['activities']->isNotEmpty())
                        <div class="scope-label">Including:</div>
                        <ul class="scope-list">
                            @foreach ($row['activities'] as $activity)
                                <li>{{ $activity }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($mode === 'consolidated' && $item && (float) $item->quantity != 1.0)
                        <div class="charge-qty">{{ $item->unit }} / {{ number_format($item->quantity, 2) }} × {{ number_format($item->unit_rate, 2) }}</div>
                    @endif
                </td>
                <td class="text-right charge-amount-cell"><strong>{{ number_format($amount, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
@else
    <table class="proposal-table invoice-service-table">
        <thead><tr><th style="width:6%">SL</th><th>Service / Particular</th><th style="width:22%" class="text-right">Amount</th></tr></thead>
        <tbody>
            @foreach ($serviceRows as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        <div class="service-title">{{ $row['title'] }}</div>
                        @if ($row['activities']->isNotEmpty())
                            <div class="scope-label">Including:</div>
                            <ul class="scope-list">
                                @foreach ($row['activities'] as $activity)
                                    <li>{{ $activity }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($row['item']->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
