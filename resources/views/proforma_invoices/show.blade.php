@extends('layouts.app', ['title' => $invoice->number])

@php $currency = $invoice->currency ?: (\App\Models\Setting::current()->default_currency ?: 'BDT'); @endphp

@section('content')
<x-page-toolbar>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('proforma-invoices.index') }}"><x-icon name="chevron-left" :size="15" /> All invoices</a>
    <x-slot:actions>
        <form method="post" action="{{ route('proforma-invoices.duplicate', $invoice) }}">@csrf
            <button class="btn btn-outline-secondary" type="submit"><x-icon name="copy" :size="16" /> Duplicate</button>
        </form>
        <a class="btn btn-outline-secondary" href="{{ route('proforma-invoices.edit', $invoice) }}"><x-icon name="edit" :size="16" /> Edit</a>
        <a class="btn btn-outline-primary" href="{{ route('proforma-invoices.email.create', $invoice) }}"><x-icon name="send" :size="16" /> Send Email</a>
        <a class="btn btn-primary" href="{{ route('proforma-invoices.pdf', $invoice) }}"><x-icon name="download" :size="16" /> Download PDF</a>
        @can('delete', $invoice)
            @if ($invoice->wasEmailed())
                <button type="button" class="btn btn-outline-danger" data-strong-delete
                    data-action="{{ route('proforma-invoices.destroy', $invoice) }}"
                    data-title="Delete {{ $invoice->number }}?"
                    data-message="This invoice was already emailed and may be QR-verified. Deleting archives it and preserves its number and verification. This cannot be undone.">
                    <x-icon name="trash" :size="16" /> Delete
                </button>
            @else
                <form method="post" action="{{ route('proforma-invoices.destroy', $invoice) }}" data-confirm="Delete draft {{ $invoice->number }}? This can’t be undone.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit"><x-icon name="trash" :size="16" /> Delete</button>
                </form>
            @endif
        @endcan
    </x-slot:actions>
</x-page-toolbar>

<div class="detail-hero mb-3">
    <span class="dh-badge" style="background:var(--invoice-050);color:var(--invoice)"><x-icon name="invoice" :size="24" /></span>
    <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="h4 mb-0">{{ $invoice->number }}</h1>
            <x-email-status :deliveries="$invoice->emailDeliveries" />
            <span class="badge-soft b-invoice">{{ \App\Models\ProformaInvoice::PRESENTATIONS[$invoice->charge_presentation] ?? 'Itemized' }}</span>
        </div>
        <div class="text-secondary mt-1">{{ $invoice->date->format('d M Y') }} · {{ $invoice->client?->company_name ?? ($invoice->client_snapshot['company_name'] ?? '—') }}</div>
    </div>
    <div class="text-end">
        <div class="cell-sub">Total Payable</div>
        <div class="money" style="font-size:20px"><span class="cur">{{ $currency }}</span>{{ number_format($invoice->total, 2) }}</div>
    </div>
</div>

@include('documents.preview', ['document' => $invoice, 'type' => 'invoice'])
@include('document_emails.history', ['deliveries' => $invoice->emailDeliveries])
@endsection
