@extends('layouts.app', ['title' => 'New Catalogue Item'])
@section('content')
<x-page-toolbar title="New Catalogue Item" subtitle="Add a standard, program, service or package to the global catalogue.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('services.index') }}"><x-icon name="chevron-left" :size="15" /> Catalogue</a>
</x-page-toolbar>
<form method="post" action="{{ route('catalogue-standards.store') }}">
    @include('standards._form')
</form>
@endsection
