@extends('layouts.app', ['title' => 'Edit '.$invoice->number])

@section('content')
<x-page-toolbar title="Edit Proforma Invoice" subtitle="{{ $invoice->number }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('proforma-invoices.show', $invoice) }}"><x-icon name="chevron-left" :size="15" /> Back to invoice</a>
</x-page-toolbar>

<form method="post" action="{{ route('proforma-invoices.update', $invoice) }}" data-loading>
    @include('documents.form', ['type' => 'invoice'])
</form>
@endsection
