@extends('layouts.app', ['title' => 'Proforma Invoices'])

@section('content')
<x-page-toolbar title="Proforma Invoices" subtitle="Payment-ready proforma invoices.">
    <x-slot:actions>
        <form class="d-flex gap-2 flex-wrap" method="get">
            <div class="search-box">
                <x-icon name="search" :size="16" />
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search number or client">
            </div>
            <select class="form-select" style="max-width:160px" name="email" onchange="this.form.submit()">
                <option value="">Any email</option>
                <option value="sent" @selected(request('email')==='sent')>Sent</option>
                <option value="not_sent" @selected(request('email')==='not_sent')>Not sent</option>
                <option value="failed" @selected(request('email')==='failed')>Failed</option>
            </select>
            @if (request('search') || request('email'))
                <a class="btn btn-outline-secondary" href="{{ route('proforma-invoices.index') }}" title="Clear"><x-icon name="x" :size="16" /></a>
            @endif
            <a class="btn btn-primary" href="{{ route('proforma-invoices.create') }}"><x-icon name="plus" :size="16" /> New Invoice</a>
        </form>
    </x-slot:actions>
</x-page-toolbar>

@include('documents.index_table', ['documents' => $invoices, 'type' => 'invoice'])
@endsection
