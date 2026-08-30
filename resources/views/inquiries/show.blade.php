@extends('layouts.app', ['title' => 'Inquiry — '.$inquiry->name])

@section('content')
<x-page-toolbar title="Website Inquiry" subtitle="Review and act on a public proposal request.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('inquiries.index') }}"><x-icon name="chevron-left" :size="15" /> All Inquiries</a>
    @can('delete', $inquiry)
        <form method="post" action="{{ route('inquiries.destroy', $inquiry) }}" class="d-inline" data-confirm="Delete this inquiry? This can’t be undone.">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm mb-1" type="submit"><x-icon name="trash" :size="15" /> Delete</button>
        </form>
    @endcan
</x-page-toolbar>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="fw-semibold">Inquiry Details</div>
                <span class="badge-soft {{ $inquiry->status === 'converted' ? 'b-ok' : ($inquiry->status === 'new' ? 'b-warn' : 'b-neutral') }}">{{ $inquiry->statusLabel() }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-secondary fw-normal">Name</dt><dd class="col-sm-8 fw-semibold">{{ $inquiry->name }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Company</dt><dd class="col-sm-8">{{ $inquiry->company ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Email</dt><dd class="col-sm-8"><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></dd>
                <dt class="col-sm-4 text-secondary fw-normal">Phone</dt><dd class="col-sm-8">{{ $inquiry->phone ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Service Interested In</dt><dd class="col-sm-8">{{ $inquiry->service ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Message</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $inquiry->message ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Received</dt><dd class="col-sm-8">{{ $inquiry->created_at?->format('d M Y, H:i') }}</dd>
            </dl>
        </div></div>

        <div class="card mt-3"><div class="card-body">
            <div class="fw-semibold mb-2">Status</div>
            <form method="post" action="{{ route('inquiries.status', $inquiry) }}" class="d-flex flex-wrap gap-2">
                @csrf @method('patch')
                @foreach ($statuses as $s)
                    <button class="btn btn-sm {{ $inquiry->status === $s ? 'btn-primary' : 'btn-outline-secondary' }}" name="status" value="{{ $s }}">{{ ucfirst($s) }}</button>
                @endforeach
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <div class="fw-semibold mb-1">Convert to Quotation</div>
            <p class="text-secondary small mb-3">Matched service: <strong>{{ $matchedService }}</strong>@if (! $matchedStandard) <span class="text-secondary">(no catalogue match — pick on the form)</span>@endif</p>

            <form method="post" action="{{ route('inquiries.quotation', $inquiry) }}">
                @csrf
                <label class="form-label fw-semibold">Client</label>
                <select class="form-select mb-2" name="client_id" required>
                    <option value="">— select existing client —</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}" @selected($suggestedClients->firstWhere('id', $c->id))>{{ $c->company_name }}</option>
                    @endforeach
                </select>
                @if ($suggestedClients->isNotEmpty())
                    <div class="form-hint mb-2">Suggested match{{ $suggestedClients->count() > 1 ? 'es' : '' }}: {{ $suggestedClients->pluck('company_name')->implode(', ') }}</div>
                @endif
                @error('client_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100" type="submit"><x-icon name="quotation" :size="16" /> Prepare Quotation</button>
                <div class="form-hint mt-2">Opens the normal quotation form, prefilled. No number is used until you save.</div>
            </form>

            <hr class="my-3">

            <div class="fw-semibold mb-1">No matching client?</div>
            <form method="post" action="{{ route('inquiries.client', $inquiry) }}">
                @csrf
                <button class="btn btn-outline-secondary w-100" type="submit"><x-icon name="clients" :size="16" /> Create Client from Inquiry</button>
                <div class="form-hint mt-2">Prefills the client form with this company &amp; contact — you review and save.</div>
            </form>
        </div></div>
    </div>
</div>
@endsection
