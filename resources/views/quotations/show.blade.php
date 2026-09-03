@extends('layouts.app', ['title' => $quotation->number])

@php $currency = \App\Models\Setting::current()->default_currency ?: 'BDT'; @endphp

@section('content')
<x-page-toolbar>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('quotations.index') }}"><x-icon name="chevron-left" :size="15" /> All quotations</a>
    <x-slot:actions>
        <form method="post" action="{{ route('quotations.duplicate', $quotation) }}">@csrf
            <button class="btn btn-outline-secondary" type="submit"><x-icon name="copy" :size="16" /> Duplicate</button>
        </form>
        <a class="btn btn-outline-secondary" href="{{ route('quotations.edit', $quotation) }}"><x-icon name="edit" :size="16" /> Edit</a>
        <a class="btn btn-outline-primary" href="{{ route('quotations.email.create', $quotation) }}"><x-icon name="send" :size="16" /> Send Email</a>
        <a class="btn btn-primary" href="{{ route('quotations.pdf', $quotation) }}"><x-icon name="download" :size="16" /> Download PDF</a>
        @can('delete', $quotation)
            @if ($quotation->wasEmailed())
                <button type="button" class="btn btn-outline-danger" data-strong-delete
                    data-action="{{ route('quotations.destroy', $quotation) }}"
                    data-title="Delete {{ $quotation->number }}?"
                    data-message="This quotation was already emailed and may be QR-verified. Deleting archives it and preserves its number and verification. This cannot be undone.">
                    <x-icon name="trash" :size="16" /> Delete
                </button>
            @else
                <form method="post" action="{{ route('quotations.destroy', $quotation) }}" data-confirm="Delete draft {{ $quotation->number }}? This can’t be undone.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit"><x-icon name="trash" :size="16" /> Delete</button>
                </form>
            @endif
        @endcan
    </x-slot:actions>
</x-page-toolbar>

<div class="detail-hero mb-3">
    <span class="dh-badge" style="background:var(--quote-050);color:var(--quote)"><x-icon name="quotation" :size="24" /></span>
    <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="h4 mb-0">{{ $quotation->number }}</h1>
            <x-email-status :deliveries="$quotation->emailDeliveries" />
            @php $cm = ['draft'=>'b-neutral','sent'=>'b-info','won'=>'b-ok','lost'=>'b-danger'][$quotation->commercial_status] ?? 'b-neutral'; @endphp
            <span class="badge-soft {{ $cm }}">{{ $quotation->commercialStatusLabel() }}</span>
        </div>
        <div class="text-secondary mt-1">{{ $quotation->date->format('d M Y') }} · {{ $quotation->client?->company_name ?? ($quotation->client_snapshot['company_name'] ?? '—') }}</div>
    </div>
    <div class="text-end">
        <div class="cell-sub">Total</div>
        <div class="money" style="font-size:20px"><span class="cur">{{ $currency }}</span>{{ number_format($quotation->total, 2) }}</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        @include('documents.commercial_status_card', [
            'document' => $quotation,
            'statusRoute' => route('quotations.status', $quotation),
            'markSentRoute' => route('quotations.mark-sent', $quotation),
            'scheduleParam' => ['quotation' => $quotation->id],
        ])
    </div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <span class="eyebrow">Commercial Note</span>
        <p class="cell-sub mt-2 mb-0">A quotation is a commercial offer. Payments and receivables are tracked on the Proforma Invoice, not the quotation.</p>
        @if ($quotation->invoices->isNotEmpty())
            <div class="mt-2"><span class="cell-sub">Linked invoice(s):</span>
                @foreach ($quotation->invoices as $inv)<a class="badge-soft b-invoice ms-1" href="{{ route('proforma-invoices.show', $inv) }}">{{ $inv->number }}</a>@endforeach
            </div>
        @endif
    </div></div></div>
</div>

@include('documents.preview', ['document' => $quotation, 'type' => 'quotation'])
@include('document_emails.history', ['deliveries' => $quotation->emailDeliveries])
@include('partials.change_history', ['histories' => $quotation->histories])
@endsection
