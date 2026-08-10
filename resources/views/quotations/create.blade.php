@extends('layouts.app', ['title' => 'New Quotation'])

@section('content')
<x-page-toolbar title="New Quotation" subtitle="Client → Service → Amount → Preview.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('quotations.index') }}"><x-icon name="chevron-left" :size="15" /> All quotations</a>
</x-page-toolbar>

<form method="post" action="{{ route('quotations.store') }}" data-loading>
    @include('documents.form', ['type' => 'quotation'])
</form>
@endsection
