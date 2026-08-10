@extends('layouts.app', ['title' => 'New Proforma Invoice'])

@section('content')
<x-page-toolbar title="New Proforma Invoice" subtitle="Client → Service → Amount → Preview.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('proforma-invoices.index') }}"><x-icon name="chevron-left" :size="15" /> All invoices</a>
</x-page-toolbar>

<form method="post" action="{{ route('proforma-invoices.store') }}" data-loading>
    @include('documents.form', ['type' => 'invoice'])
</form>
@endsection
