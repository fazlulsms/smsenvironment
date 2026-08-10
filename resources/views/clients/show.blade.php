@extends('layouts.app', ['title' => $client->company_name])

@php $currency = \App\Models\Setting::current()->default_currency ?: 'BDT'; @endphp

@section('content')
<x-page-toolbar>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('clients.index') }}"><x-icon name="chevron-left" :size="15" /> All clients</a>
    <x-slot:actions>
        <a class="btn btn-outline-secondary" href="{{ route('clients.edit', $client) }}"><x-icon name="edit" :size="16" /> Edit</a>
        <a class="btn btn-outline-primary" href="{{ route('proforma-invoices.create', ['client_id' => $client->id]) }}"><x-icon name="invoice" :size="16" /> New Invoice</a>
        <a class="btn btn-primary" href="{{ route('quotations.create', ['client_id' => $client->id]) }}"><x-icon name="quotation" :size="16" /> New Quotation</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="detail-hero mb-3">
    <span class="dh-badge" style="background:var(--client-050);color:var(--client)">{{ strtoupper(mb_substr($client->company_name, 0, 1)) }}</span>
    <div class="flex-grow-1">
        <h1 class="h4 mb-1">{{ $client->company_name }}</h1>
        <div class="text-secondary">
            @if ($client->parent_company){{ $client->parent_company }} · @endif
            {{ collect([$client->city, $client->country])->filter()->implode(', ') ?: 'Location not set' }}
        </div>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <span class="badge-soft b-quote">{{ $client->quotations_count }} quotations</span>
            <span class="badge-soft b-invoice">{{ $client->proforma_invoices_count }} invoices</span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-head"><h2>Contact</h2></div>
            <div class="card-body">
                <dl class="kv mb-0">
                    <dt>Person</dt><dd>{{ $client->contact_person ?: '—' }}</dd>
                    <dt>Designation</dt><dd>{{ $client->designation ?: '—' }}</dd>
                    <dt>Department</dt><dd>{{ $client->department ?: '—' }}</dd>
                    <dt>Email</dt><dd>{{ $client->email ?: '—' }}</dd>
                    <dt>Phone</dt><dd>{{ $client->phone ?: '—' }}</dd>
                    <dt>Website</dt><dd>{{ $client->website ?: '—' }}</dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Address</h2></div>
            <div class="card-body">
                <dl class="kv mb-0">
                    <dt>Street</dt><dd>{{ $client->address ?: '—' }}</dd>
                    <dt>City</dt><dd>{{ $client->city ?: '—' }}</dd>
                    <dt>Postal Code</dt><dd>{{ $client->postal_code ?: '—' }}</dd>
                    <dt>Country</dt><dd>{{ $client->country ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card mb-3">
            <div class="card-head"><h2>Recent Quotations</h2>
                <a class="card-link" href="{{ route('quotations.create', ['client_id' => $client->id]) }}">+ New</a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Date</th><th class="num">Amount</th><th>Email</th></tr></thead>
                    <tbody>
                    @forelse ($client->quotations as $q)
                        <tr>
                            <td><a class="row-title" href="{{ route('quotations.show', $q) }}">{{ $q->number }}</a></td>
                            <td class="cell-sub">{{ $q->date?->format('d M Y') }}</td>
                            <td class="num money"><span class="cur">{{ $currency }}</span>{{ number_format($q->total, 2) }}</td>
                            <td><x-email-status :deliveries="$q->emailDeliveries" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">No quotations yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card table-card">
            <div class="card-head"><h2>Recent Proforma Invoices</h2>
                <a class="card-link" href="{{ route('proforma-invoices.create', ['client_id' => $client->id]) }}">+ New</a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Invoice No.</th><th>Date</th><th class="num">Total</th><th>Email</th></tr></thead>
                    <tbody>
                    @forelse ($client->proformaInvoices as $inv)
                        <tr>
                            <td><a class="row-title" href="{{ route('proforma-invoices.show', $inv) }}">{{ $inv->number }}</a></td>
                            <td class="cell-sub">{{ $inv->date?->format('d M Y') }}</td>
                            <td class="num money"><span class="cur">{{ $currency }}</span>{{ number_format($inv->total, 2) }}</td>
                            <td><x-email-status :deliveries="$inv->emailDeliveries" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">No invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
