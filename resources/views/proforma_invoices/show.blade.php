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

@php
    $received = $invoice->receivedAmount();
    $due = $invoice->dueAmount();
    $payStatus = $invoice->paymentStatus();
    $statusMeta = [
        'draft' => ['Draft', 'b-neutral'], 'sent' => ['Sent', 'b-info'],
        'won' => ['Won', 'b-ok'], 'lost' => ['Lost', 'b-danger'],
    ][$invoice->commercial_status] ?? ['Draft', 'b-neutral'];
    $payMeta = ['unpaid' => ['Unpaid', 'b-warn'], 'partial' => ['Partially Paid', 'b-info'], 'paid' => ['Paid', 'b-ok']][$payStatus];
@endphp

<div class="row g-3 mb-3">
    {{-- Commercial status (shared with quotations) --}}
    <div class="col-lg-5">
        @include('documents.commercial_status_card', [
            'document' => $invoice,
            'statusRoute' => route('proforma-invoices.status', $invoice),
            'markSentRoute' => route('proforma-invoices.mark-sent', $invoice),
            'scheduleParam' => ['invoice' => $invoice->id],
        ])
    </div>

    {{-- Receivables --}}
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="eyebrow">Receivables</span>
                <span class="badge-soft {{ $payMeta[1] }}">{{ $payMeta[0] }}</span>
            </div>
            <div class="row text-center g-2 mb-2">
                <div class="col-4"><div class="cell-sub">Invoice</div><div class="money"><span class="cur">{{ $currency }}</span>{{ number_format($invoice->total, 2) }}</div></div>
                <div class="col-4"><div class="cell-sub">Received</div><div class="money text-success"><span class="cur">{{ $currency }}</span>{{ number_format($received, 2) }}</div></div>
                <div class="col-4"><div class="cell-sub">Due</div><div class="money {{ $due > 0 ? 'text-danger' : '' }}"><span class="cur">{{ $currency }}</span>{{ number_format($due, 2) }}</div></div>
            </div>
            @if ($due > 0.001)
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#recordPaymentModal"><x-icon name="plus" :size="15" /> Record Payment</button>
            @endif
            @if ($invoice->payments->isNotEmpty())
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>By</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($invoice->payments as $p)
                            <tr>
                                <td class="cell-sub">{{ $p->received_date->format('d M Y') }}</td>
                                <td class="money"><span class="cur">{{ $p->currency }}</span>{{ number_format($p->amount, 2) }}</td>
                                <td class="cell-sub">{{ $p->method ?: '—' }}</td>
                                <td class="cell-sub">{{ $p->reference ?: '—' }}</td>
                                <td class="cell-sub">{{ $p->recorder?->name ?? '—' }}</td>
                                <td class="text-end">
                                    @can('delete-payments')
                                        <form method="post" action="{{ route('proforma-invoices.payments.destroy', [$invoice, $p]) }}" data-confirm="Remove this payment entry?">
                                            @csrf @method('DELETE')
                                            <button class="btn-icon text-danger" type="submit" title="Remove"><x-icon name="trash" :size="15" /></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div></div>
    </div>
</div>

{{-- Record payment modal --}}
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="{{ route('proforma-invoices.payments.store', $invoice) }}" data-no-loading>
            @csrf
            <div class="modal-header"><h5 class="modal-title">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Amount ({{ $currency }})</label><input class="form-control" type="number" step="0.01" min="0.01" max="{{ $due }}" name="amount" value="{{ number_format($due, 2, '.', '') }}" required></div>
                    <div class="col-6"><label class="form-label">Received Date</label><input class="form-control" type="date" name="received_date" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-6"><label class="form-label">Method</label>
                        <select class="form-select" name="method">
                            <option value="">—</option>
                            @foreach (\App\Models\InvoicePayment::METHODS as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Txn / cheque no."></div>
                    <div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note"></div>
                </div>
                <div class="form-text mt-2">Outstanding due: <b>{{ $currency }} {{ number_format($due, 2) }}</b>. Overpayment is prevented.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Record Payment</button></div>
        </form>
    </div>
</div>

@include('documents.preview', ['document' => $invoice, 'type' => 'invoice'])
@include('document_emails.history', ['deliveries' => $invoice->emailDeliveries])
@include('partials.change_history', ['histories' => $invoice->histories])
@endsection
