@extends('layouts.app', ['title' => $quotation->number])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $quotation->number }}</h1>
        <div class="text-secondary">{{ $quotation->date->format('d M Y') }} - {{ $quotation->client->company_name }}</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('quotations.pdf', $quotation) }}">Download PDF</a>
        <a class="btn btn-outline-secondary" href="{{ route('quotations.edit', $quotation) }}">Edit</a>
        <form method="post" action="{{ route('quotations.duplicate', $quotation) }}">@csrf <button class="btn btn-outline-secondary">Duplicate</button></form>
    </div>
</div>
@include('documents.preview', ['document' => $quotation, 'type' => 'quotation'])
@endsection
