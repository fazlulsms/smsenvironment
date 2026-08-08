@extends('layouts.app', ['title' => 'Edit Quotation'])

@section('content')
<h1 class="h3 mb-3">Edit Quotation</h1>
<form class="panel p-3" method="post" action="{{ route('quotations.update', $quotation) }}">
    @include('documents.form', ['type' => 'quotation'])
</form>
@endsection
