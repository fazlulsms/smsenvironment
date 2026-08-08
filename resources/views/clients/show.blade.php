@extends('layouts.app', ['title' => $client->company_name])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">{{ $client->company_name }}</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('quotations.create', ['client_id' => $client->id]) }}">New Quotation</a>
        <a class="btn btn-outline-primary" href="{{ route('proforma-invoices.create', ['client_id' => $client->id]) }}">New Invoice</a>
        <a class="btn btn-outline-secondary" href="{{ route('clients.edit', $client) }}">Edit</a>
    </div>
</div>
<div class="panel p-3 mb-4">
    <div class="row g-3">
        <div class="col-md-4"><div class="muted-label">Parent</div>{{ $client->parent_company ?: '-' }}</div>
        <div class="col-md-4"><div class="muted-label">Contact</div>{{ $client->contact_person ?: '-' }}<div class="text-secondary small">{{ $client->designation }}</div></div>
        <div class="col-md-4"><div class="muted-label">Email / Phone</div>{{ $client->email ?: '-' }}<div class="text-secondary small">{{ $client->phone }}</div></div>
        <div class="col-12"><div class="muted-label">Address</div>{{ $client->address }}</div>
    </div>
</div>
@endsection
