@extends('layouts.app', ['title' => 'Edit Proforma Invoice'])

@section('content')
<h1 class="h3 mb-3">Edit Proforma Invoice</h1>
<form class="panel p-3" method="post" action="{{ route('proforma-invoices.update', $invoice) }}">
    @include('documents.form', ['type' => 'invoice'])
</form>
@endsection
