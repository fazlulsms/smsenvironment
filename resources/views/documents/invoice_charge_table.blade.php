@php
    // SERVICE = the saved commercial title (charge_title). CHARGE FOR = the saved
    // description (consolidated), components (breakdown) or itemised rows.
    // Header/totals/bank/QR/footer are shared regardless of presentation mode.
    $mode = $invoice->charge_presentation ?? 'itemized';
    $first = $serviceRows->first();
    $firstItem = $first['item'] ?? null;
    $serviceName = $invoice->charge_title ?: ($first['title'] ?? 'Environmental Services');
@endphp

<div class="commercial-block">
    <div class="cf-label">Service</div>
    <div class="cf-service">{{ $serviceName }}</div>

    <div class="cf-label">Charge For</div>
    @if ($mode === 'consolidated')
        <div class="cf-desc">{{ $firstItem?->description ?: $serviceName }}</div>
    @elseif ($mode === 'component_breakdown')
        <div class="cf-desc">Including:</div>
        <ul class="cf-list">
            @foreach (($first['activities'] ?? collect()) as $activity)
                <li>{{ $activity }}</li>
            @endforeach
        </ul>
    @else
        <table class="proposal-table invoice-service-table cf-table">
            <thead><tr><th style="width:6%">SL</th><th>Description</th><th style="width:24%" class="text-right">Amount</th></tr></thead>
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
</div>
