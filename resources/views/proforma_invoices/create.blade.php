@extends('layouts.app', ['title' => 'New Proforma Invoice'])

@section('content')
<h1 class="h3 mb-3">New Proforma Invoice</h1>
<form class="panel p-3" method="post" action="{{ route('proforma-invoices.store') }}">
    @include('documents.form', ['type' => 'invoice'])
</form>
@endsection
