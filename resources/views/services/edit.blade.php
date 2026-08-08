@extends('layouts.app', ['title' => 'Edit Service'])

@section('content')
<h1 class="h3 mb-3">Edit Service</h1>
<form class="panel p-3" method="post" action="{{ route('services.update', $service) }}">@method('put') @include('services._form')</form>
@endsection
