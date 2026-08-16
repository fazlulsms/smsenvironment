@extends('layouts.app', ['title' => 'Edit Catalogue Item'])
@section('content')
<x-page-toolbar title="{{ $standard->name }}" subtitle="{{ $standard->category?->name }} · catalogue item">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('services.index') }}"><x-icon name="chevron-left" :size="15" /> Catalogue</a>
</x-page-toolbar>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="post" action="{{ route('catalogue-standards.update', $standard) }}">
    @include('standards._form')
</form>
@endsection
