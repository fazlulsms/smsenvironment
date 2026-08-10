@extends('layouts.app', ['title' => 'Add Service'])

@section('content')
<x-page-toolbar title="Add Service" subtitle="Standalone service, bundle or consolidated professional service.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('services.index') }}"><x-icon name="chevron-left" :size="15" /> All services</a>
</x-page-toolbar>

<form method="post" action="{{ route('services.store') }}" data-loading>@include('services._form')</form>
@endsection
