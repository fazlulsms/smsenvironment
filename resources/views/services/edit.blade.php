@extends('layouts.app', ['title' => 'Edit '.$service->name])

@section('content')
<x-page-toolbar title="Edit Service" subtitle="{{ $service->name }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('services.index') }}"><x-icon name="chevron-left" :size="15" /> All services</a>
</x-page-toolbar>

<form method="post" action="{{ route('services.update', $service) }}" data-loading>@method('put') @include('services._form')</form>
@endsection
