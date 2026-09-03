@extends('layouts.app', ['title' => 'Dashboard'])

@php
    // Render a currency-grouped monetary KPI as "BDT x / USD y" lines.
    $money = function (array $rows) {
        if (empty($rows)) return ['—'];
        return collect($rows)->map(fn ($r) => ($r['currency'] ?? 'BDT').' '.number_format($r['value'], 0))->all();
    };
    $moneyMap = function (array $map) {
        if (empty($map)) return ['—'];
        return collect($map)->map(fn ($v, $c) => strtoupper($c).' '.number_format($v, 0))->values()->all();
    };
    $count = fn (array $rows) => collect($rows)->sum('count');
    $sk = $scheduleKpis; $rk = $receivableKpis;
    $maxMonthly = max(1, collect($monthlyInvoiced)->max('value'));
@endphp

@section('content')
<x-page-toolbar title="Management Dashboard" subtitle="{{ $period['from']->format('d M Y') }} — {{ $period['to']->format('d M Y') }}">
    <x-slot:actions>
        <div class="btn-group btn-group-sm">
            @foreach (['today'=>'Today','month'=>'This Month','year'=>'This Year'] as $key => $label)
                <a class="btn {{ $preset === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('dashboard', ['period' => $key]) }}">{{ $label }}</a>
            @endforeach
        </div>
        <form class="d-flex gap-1" method="get">
            <input type="hidden" name="period" value="custom">
            <input class="form-control form-control-sm" type="date" name="from" value="{{ request('from', $period['from']->toDateString()) }}">
            <input class="form-control form-control-sm" type="date" name="to" value="{{ request('to', $period['to']->toDateString()) }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit">Apply</button>
        </form>
    </x-slot:actions>
</x-page-toolbar>

{{-- Invoice KPIs --}}
<div class="row g-3 mb-1">
    @foreach ([
        ['Commercial Offers Sent', $invoiceKpis['sent'], 'send', 'b-info'],
        ['Won', $invoiceKpis['won'], 'check', 'b-ok'],
        ['Lost', $invoiceKpis['lost'], 'x', 'b-danger'],
        ['Total Invoiced', $invoiceKpis['invoiced'], 'invoice', 'b-neutral'],
    ] as [$label, $rows, $icon, $cls])
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between"><span class="cell-sub">{{ $label }}</span><span class="badge-soft {{ $cls }}">{{ $count($rows) }}</span></div>
                @foreach ($money($rows) as $line)<div class="h5 mb-0 mt-1">{{ $line }}</div>@endforeach
            </div></div>
        </div>
    @endforeach
</div>

{{-- Receivables KPIs --}}
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="cell-sub">Received (period)</span>@foreach ($moneyMap($rk['received']) as $l)<div class="h5 mb-0 mt-1 text-success">{{ $l }}</div>@endforeach</div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="cell-sub">Outstanding Due</span>@foreach ($moneyMap($rk['due']) as $l)<div class="h5 mb-0 mt-1 text-danger">{{ $l }}</div>@endforeach</div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="cell-sub">Collection %</span><div class="h4 mb-0 mt-1">{{ $rk['collection_pct'] !== null ? $rk['collection_pct'].'%' : '—' }}</div><div class="cell-sub">of period invoiced (BDT-equiv.)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="cell-sub">Clients</span><div class="h4 mb-0 mt-1">{{ $clientsCount }}</div></div></div></div>
</div>

{{-- Schedule KPIs --}}
<div class="row g-3 mb-1">
    @foreach ([
        ['Scheduled', $sk['scheduled']], ['Completed', $sk['completed']],
        ['Assessment Days', $sk['assessment_days']], ['Assessor-Days', $sk['assessor_days']],
        ['Upcoming Reassess.', $sk['upcoming']], ['Overdue', $sk['overdue']],
    ] as [$label, $val])
        <div class="col-4 col-lg-2"><div class="card h-100"><div class="card-body py-3"><span class="cell-sub">{{ $label }}</span><div class="h4 mb-0 mt-1">{{ $val }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-3 mt-0">
    {{-- Monthly invoice value chart --}}
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-body">
            <span class="eyebrow">Monthly Invoice Value <span class="cell-sub">(last 12 months, BDT-equivalent)</span></span>
            <div class="d-flex align-items-end gap-2 mt-3" style="height:180px">
                @foreach ($monthlyInvoiced as $m)
                    <div class="flex-fill text-center d-flex flex-column justify-content-end" style="min-width:0">
                        <div title="BDT {{ number_format($m['value']) }}" style="background:linear-gradient(180deg,var(--entity-secondary,#2da46f),var(--entity-primary,#1f6f4a));border-radius:4px 4px 0 0;height:{{ max(2, round($m['value'] / $maxMonthly * 150)) }}px"></div>
                        <div class="cell-sub mt-1" style="font-size:10px">{{ $m['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div></div>
    </div>
    {{-- Service-wise report --}}
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <span class="eyebrow">Service-wise (BDT-equiv.)</span>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0"><thead><tr><th>Service</th><th class="num">Offers</th><th class="num">Won</th><th class="num">Due</th></tr></thead><tbody>
                @forelse ($serviceReport as $r)
                    <tr><td class="cell-sub">{{ \Illuminate\Support\Str::limit($r['service'], 22) }}</td><td class="num">{{ $r['offers'] }}</td><td class="num">{{ number_format($r['won_value']) }}</td><td class="num">{{ number_format($r['due']) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="cell-sub text-center py-3">No data for this period.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3 mt-0">
    {{-- Assessor report --}}
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <span class="eyebrow">Assessor Report</span>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0"><thead><tr><th>Assessor</th><th class="num">Assign.</th><th class="num">Days</th><th class="num">Done</th></tr></thead><tbody>
                @forelse ($assessorReport as $r)
                    <tr><td class="cell-sub">{{ $r['assessor'] }}</td><td class="num">{{ $r['assignments'] }}</td><td class="num">{{ $r['assessment_days'] }}</td><td class="num">{{ $r['completed'] }}</td></tr>
                @empty
                    <tr><td colspan="4" class="cell-sub text-center py-3">No assignments in this period.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </div></div>
    </div>
    {{-- Recent invoices --}}
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between"><span class="eyebrow">Recent Invoices</span><a class="cell-sub" href="{{ route('receivables.index') }}">Receivables →</a></div>
            <div class="table-responsive mt-2">
                <table class="table table-sm mb-0"><tbody>
                @forelse ($recentInvoices as $inv)
                    <tr><td><a class="row-title" href="{{ route('proforma-invoices.show', $inv) }}">{{ $inv->number }}</a><div class="cell-sub">{{ $inv->client?->company_name ?? ($inv->client_snapshot['company_name'] ?? '—') }}</div></td>
                    <td class="num money"><span class="cur">{{ $inv->payableCurrency() }}</span>{{ number_format($inv->total, 0) }}</td></tr>
                @empty
                    <tr><td class="cell-sub text-center py-3">No invoices yet.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </div></div>
    </div>
</div>
@endsection
