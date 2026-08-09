@extends('layouts.app', ['title' => $invoice->number])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $invoice->number }}</h1>
        <div class="text-secondary">{{ $invoice->date->format('d M Y') }} - {{ $invoice->client->company_name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('proforma-invoices.pdf', $invoice) }}">Download PDF</a>
        <a class="btn btn-outline-primary" href="{{ route('proforma-invoices.email.create', $invoice) }}">Send Email</a>
        <a class="btn btn-outline-secondary" href="{{ route('proforma-invoices.edit', $invoice) }}">Edit</a>
        <form method="post" action="{{ route('proforma-invoices.duplicate', $invoice) }}">@csrf <button class="btn btn-outline-secondary">Duplicate</button></form>
    </div>
</div>
@include('documents.preview', ['document' => $invoice, 'type' => 'invoice'])
@include('document_emails.history', ['deliveries' => $invoice->emailDeliveries])
@endsection
