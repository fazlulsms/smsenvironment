@extends('layouts.app', ['title' => 'Receivables'])

@section('content')
<x-page-toolbar title="Receivables" subtitle="Invoice collection and commercial outcome.">
    <x-slot:actions>
        <form class="d-flex gap-2 flex-wrap" method="get">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input class="form-control form-control-sm" type="date" name="from" value="{{ request('from') }}">
            <input class="form-control form-control-sm" type="date" name="to" value="{{ request('to') }}">
            <div class="search-box"><x-icon name="search" :size="16" /><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Invoice no."></div>
            <button class="btn btn-outline-secondary btn-sm" type="submit">Filter</button>
        </form>
    </x-slot:actions>
</x-page-toolbar>

<div class="d-flex gap-2 flex-wrap mb-3">
    @foreach (['all'=>'All','unpaid'=>'Unpaid','partial'=>'Partially Paid','paid'=>'Paid','won'=>'Won','lost'=>'Lost','sent'=>'Sent'] as $key => $label)
        <a class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('receivables.index', array_merge(request()->only('from','to','search'), ['filter' => $key])) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Invoice</th><th>Client</th><th>Date</th><th class="num">Amount</th><th class="num">Received</th><th class="num">Due</th><th>Payment</th><th>Commercial</th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                @php
                    $rec = (float) ($inv->received_sum ?? 0); $due = round((float)$inv->total - $rec, 2);
                    $ps = $rec <= 0 ? ['Unpaid','b-warn'] : ($rec + 0.001 >= (float)$inv->total ? ['Paid','b-ok'] : ['Partially Paid','b-info']);
                    $cs = ['draft'=>['Draft','b-neutral'],'sent'=>['Sent','b-info'],'won'=>['Won','b-ok'],'lost'=>['Lost','b-danger']][$inv->commercial_status] ?? ['Draft','b-neutral'];
                    $cur = $inv->payableCurrency();
                @endphp
                <tr>
                    <td><a class="row-title" href="{{ route('proforma-invoices.show', $inv) }}">{{ $inv->number }}</a></td>
                    <td>{{ $inv->client?->company_name ?? ($inv->client_snapshot['company_name'] ?? '—') }}</td>
                    <td class="cell-sub">{{ $inv->date?->format('d M Y') }}</td>
                    <td class="num money"><span class="cur">{{ $cur }}</span>{{ number_format($inv->total, 2) }}</td>
                    <td class="num money text-success">{{ number_format($rec, 2) }}</td>
                    <td class="num money {{ $due > 0 ? 'text-danger' : '' }}">{{ number_format($due, 2) }}</td>
                    <td><span class="badge-soft {{ $ps[1] }}">{{ $ps[0] }}</span></td>
                    <td><span class="badge-soft {{ $cs[1] }}">{{ $cs[0] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="invoice" title="No invoices" message="Invoices will appear here with their receivable status." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($invoices->hasPages())<div class="mt-3">{{ $invoices->links() }}</div>@endif
@endsection
