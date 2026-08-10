@extends('layouts.app', ['title' => 'Email History'])

@section('content')
<x-page-toolbar title="Email History" subtitle="Every quotation and proforma invoice delivery.">
    <x-slot:actions>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge-soft b-ok"><x-icon name="check" :size="12" />{{ $sentCount }} sent</span>
            @if ($failedCount)<span class="badge-soft b-danger"><x-icon name="alert" :size="12" />{{ $failedCount }} failed</span>@endif
        </div>
    </x-slot:actions>
</x-page-toolbar>

<form class="d-flex flex-wrap gap-2 mb-3" method="get">
    <select class="form-select" style="max-width: 190px" name="type" onchange="this.form.submit()">
        <option value="">All documents</option>
        <option value="quotation" @selected(request('type')==='quotation')>Quotations</option>
        <option value="proforma_invoice" @selected(request('type')==='proforma_invoice')>Proforma Invoices</option>
    </select>
    <select class="form-select" style="max-width: 160px" name="status" onchange="this.form.submit()">
        <option value="">Any status</option>
        <option value="sent" @selected(request('status')==='sent')>Sent</option>
        <option value="failed" @selected(request('status')==='failed')>Failed</option>
    </select>
    @if (request('type') || request('status'))
        <a class="btn btn-outline-secondary" href="{{ route('email-deliveries.index') }}">Clear</a>
    @endif
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Document</th><th>Recipient</th><th>Subject</th>
                    <th>Sent by</th><th>When</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($deliveries as $delivery)
                <tr>
                    <td>
                        @if ($delivery->document_url)
                            <a class="row-title" href="{{ $delivery->document_url }}">{{ $delivery->document_number ?? '—' }}</a>
                        @else
                            <span class="row-title">{{ $delivery->document_number ?? '—' }}</span>
                        @endif
                        <div class="cell-sub">
                            {{ $delivery->document_type === 'quotation' ? 'Quotation' : 'Proforma Invoice' }}
                            @if ($delivery->document_client) · {{ $delivery->document_client }}@endif
                        </div>
                    </td>
                    <td>
                        {{ $delivery->to_email }}
                        @if ($delivery->cc_emails)<div class="cell-sub">CC: {{ implode(', ', $delivery->cc_emails) }}</div>@endif
                    </td>
                    <td class="cell-sub" style="max-width: 260px">{{ \Illuminate\Support\Str::limit($delivery->subject, 60) }}</td>
                    <td>{{ $delivery->sender?->name ?? 'System' }}</td>
                    <td class="cell-sub">{{ ($delivery->sent_at ?? $delivery->created_at)?->format('d M Y, g:i A') }}</td>
                    <td>
                        @if ($delivery->status === 'sent')
                            <span class="badge-soft b-ok"><x-icon name="check" :size="12" />Sent</span>
                        @else
                            <span class="badge-soft b-danger" title="{{ $delivery->error_summary }}"><x-icon name="alert" :size="12" />Failed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">
                    <x-empty-state icon="email" title="No emails sent yet"
                        message="When you send a quotation or proforma invoice, delivery records appear here." />
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($deliveries->hasPages())
    <div class="mt-3">{{ $deliveries->links() }}</div>
@endif
@endsection
