@extends('layouts.app', ['title' => 'Dashboard'])

@php
    $currency = \App\Models\Setting::current()->default_currency ?: 'BDT';
    $money = fn ($n) => number_format((float) $n, 0);
    $serviceLabel = function ($doc) {
        $names = $doc->items->map(fn ($i) => $i->service?->short_name ?: $i->service?->name ?: $i->description)->filter()->unique()->values();
        if ($names->isEmpty()) return '—';
        return $names->count() === 1 ? $names->first() : $names->first().' +'.($names->count() - 1);
    };
@endphp

@section('content')
<x-page-toolbar title="Welcome back" subtitle="Create quotations and proforma invoices in a few clicks — no manual document editing.">
    <x-slot:actions>
        <a class="btn btn-outline-secondary" href="{{ route('clients.create') }}"><x-icon name="sparkles" :size="16" /> Smart Paste Client</a>
        <a class="btn btn-primary" href="{{ route('quotations.create') }}"><x-icon name="plus" :size="16" /> New Quotation</a>
    </x-slot:actions>
</x-page-toolbar>

{{-- Metrics --}}
<div class="stat-grid mb-3">
    <x-stat-card theme="quote" icon="money" label="Quoted Value" unit="{{ $currency }}"
        value="{{ $money($quotedValue) }}" foot="{{ $currency }} {{ $money($quotedThisMonth) }} this month"
        :href="route('quotations.index')" />
    <x-stat-card theme="invoice" icon="money" label="Invoiced Value" unit="{{ $currency }}"
        value="{{ $money($invoicedValue) }}" foot="{{ $currency }} {{ $money($invoicedThisMonth) }} this month"
        :href="route('proforma-invoices.index')" />
    <x-stat-card theme="quote" icon="quotation" label="Quotations"
        value="{{ $quotationsCount }}" :href="route('quotations.index')" foot="{{ $docsThisMonth }} documents this month" />
    <x-stat-card theme="invoice" icon="invoice" label="Proforma Invoices"
        value="{{ $invoicesCount }}" :href="route('proforma-invoices.index')" />
    <x-stat-card theme="client" icon="clients" label="Clients"
        value="{{ $clientsCount }}" :href="route('clients.index')" />
    <x-stat-card theme="service" icon="services" label="Active Services"
        value="{{ $servicesCount }}" :href="route('services.index')" />
    <x-stat-card theme="ok" icon="email" label="Emails Sent"
        value="{{ $emailsSent }}" :href="route('email-deliveries.index')" foot="{{ $emailsThisMonth }} this month" />
</div>

{{-- Quick actions --}}
<div class="card mb-3">
    <div class="card-head"><h2>Quick actions</h2></div>
    <div class="card-body">
        <div class="qa-grid">
            <a class="qa qa-smart" href="{{ route('ai-draft.index') }}"><span class="qa-ico"><x-icon name="sparkles" /></span><span>AI Commercial Draft<small>Paste a WhatsApp request</small></span></a>
            <a class="qa qa-quote" href="{{ route('quotations.create') }}"><span class="qa-ico"><x-icon name="quotation" /></span><span>New Quotation<small>Draft a proposal</small></span></a>
            <a class="qa qa-invoice" href="{{ route('proforma-invoices.create') }}"><span class="qa-ico"><x-icon name="invoice" /></span><span>New Invoice<small>Proforma invoice</small></span></a>
            <a class="qa qa-client" href="{{ route('clients.create') }}"><span class="qa-ico"><x-icon name="clients" /></span><span>Add Client<small>Manual entry</small></span></a>
            <a class="qa qa-smart" href="{{ route('clients.create') }}"><span class="qa-ico"><x-icon name="sparkles" /></span><span>Smart Paste<small>Paste &amp; detect</small></span></a>
            <a class="qa qa-service" href="{{ route('services.create') }}"><span class="qa-ico"><x-icon name="services" /></span><span>Add Service<small>Library item</small></span></a>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Recent quotations --}}
    <div class="col-xl-6">
        <div class="card table-card h-100">
            <div class="card-head"><h2>Recent Quotations</h2><a class="card-link" href="{{ route('quotations.index') }}">View all</a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Client</th><th class="num">Amount</th><th>Email</th></tr></thead>
                    <tbody>
                    @forelse ($recentQuotations as $q)
                        <tr>
                            <td><a class="row-title" href="{{ route('quotations.show', $q) }}">{{ $q->number }}</a>
                                <div class="cell-sub">{{ $q->date?->format('d M Y') }} · {{ $serviceLabel($q) }}</div></td>
                            <td>{{ $q->client?->company_name ?? ($q->client_snapshot['company_name'] ?? '—') }}</td>
                            <td class="num money"><span class="cur">{{ $currency }}</span>{{ $money($q->total) }}</td>
                            <td><x-email-status :deliveries="$q->emailDeliveries" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="quotation" title="No quotations yet"
                            message="Create your first quotation to get started.">
                            <a class="btn btn-primary btn-sm" href="{{ route('quotations.create') }}"><x-icon name="plus" :size="15" /> Create Quotation</a>
                        </x-empty-state></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent invoices --}}
    <div class="col-xl-6">
        <div class="card table-card h-100">
            <div class="card-head"><h2>Recent Proforma Invoices</h2><a class="card-link" href="{{ route('proforma-invoices.index') }}">View all</a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Invoice No.</th><th>Client</th><th class="num">Total</th><th>Email</th></tr></thead>
                    <tbody>
                    @forelse ($recentInvoices as $inv)
                        <tr>
                            <td><a class="row-title" href="{{ route('proforma-invoices.show', $inv) }}">{{ $inv->number }}</a>
                                <div class="cell-sub">{{ $inv->date?->format('d M Y') }} · {{ $serviceLabel($inv) }}</div></td>
                            <td>{{ $inv->client?->company_name ?? ($inv->client_snapshot['company_name'] ?? '—') }}</td>
                            <td class="num money"><span class="cur">{{ $currency }}</span>{{ $money($inv->total) }}</td>
                            <td><x-email-status :deliveries="$inv->emailDeliveries" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="invoice" title="No invoices yet"
                            message="Create your first proforma invoice to get started.">
                            <a class="btn btn-primary btn-sm" href="{{ route('proforma-invoices.create') }}"><x-icon name="plus" :size="15" /> Create Invoice</a>
                        </x-empty-state></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($recentClients->isNotEmpty())
<div class="card mt-3">
    <div class="card-head"><h2>Recently Added Clients</h2><a class="card-link" href="{{ route('clients.index') }}">View all</a></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($recentClients as $client)
                <a class="btn btn-light btn-sm d-inline-flex align-items-center gap-2" href="{{ route('clients.show', $client) }}">
                    <span class="badge-soft b-neutral" style="width:24px;height:24px;padding:0;justify-content:center">{{ strtoupper(mb_substr($client->company_name, 0, 1)) }}</span>
                    {{ \Illuminate\Support\Str::limit($client->company_name, 32) }}
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
