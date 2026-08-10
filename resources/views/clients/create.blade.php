@extends('layouts.app', ['title' => 'Add Client'])

@section('content')
<x-page-toolbar title="Add Client" subtitle="Paste details with Smart Paste, or fill the form manually.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('clients.index') }}"><x-icon name="chevron-left" :size="15" /> All clients</a>
</x-page-toolbar>

<form method="post" action="{{ route('clients.store') }}" data-loading>
    @include('clients._form')
</form>
@endsection
