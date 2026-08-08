@extends('layouts.app', ['title' => 'New Quotation'])

@section('content')
<h1 class="h3 mb-3">New Quotation</h1>
<form class="panel p-3" method="post" action="{{ route('quotations.store') }}">
    @include('documents.form', ['type' => 'quotation'])
</form>
@endsection
