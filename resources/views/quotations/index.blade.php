@extends('layouts.app', ['title' => 'Quotations'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Quotations</h1>
    <a class="btn btn-primary" href="{{ route('quotations.create') }}">New Quotation</a>
</div>
<form class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search number or client"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>
@include('documents.index_table', ['documents' => $quotations, 'type' => 'quotation'])
@endsection
