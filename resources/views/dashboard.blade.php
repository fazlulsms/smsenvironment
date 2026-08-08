@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Office Desk</h1>
        <p class="text-secondary mb-0">Create quotations and proforma invoices without Word editing.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('quotations.create') }}">New Quotation</a>
        <a class="btn btn-outline-primary" href="{{ route('proforma-invoices.create') }}">New Proforma Invoice</a>
        <a class="btn btn-outline-secondary" href="{{ route('clients.create') }}">Add Client</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="panel p-3">
            <div class="muted-label">Clients</div>
            <div class="fs-3 fw-semibold">{{ $clientsCount }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel p-3">
            <div class="muted-label">Services</div>
            <div class="fs-3 fw-semibold">{{ $servicesCount }}</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Recent Quotations</h2>
                <a href="{{ route('quotations.index') }}">View all</a>
            </div>
            @include('documents.recent_table', ['documents' => $recentQuotations, 'type' => 'quotation'])
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Recent Invoices</h2>
                <a href="{{ route('proforma-invoices.index') }}">View all</a>
            </div>
            @include('documents.recent_table', ['documents' => $recentInvoices, 'type' => 'invoice'])
        </div>
    </div>
</div>
@endsection
