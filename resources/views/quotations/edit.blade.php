@extends('layouts.app', ['title' => 'Edit '.$quotation->number])

@section('content')
<x-page-toolbar title="Edit Quotation" subtitle="{{ $quotation->number }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('quotations.show', $quotation) }}"><x-icon name="chevron-left" :size="15" /> Back to quotation</a>
</x-page-toolbar>

<form method="post" action="{{ route('quotations.update', $quotation) }}" data-loading>
    @include('documents.form', ['type' => 'quotation'])
</form>
@endsection
