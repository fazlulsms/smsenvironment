@extends('layouts.app', ['title' => 'Add Service'])

@section('content')
<h1 class="h3 mb-3">Add Service</h1>
<form class="panel p-3" method="post" action="{{ route('services.store') }}">@include('services._form')</form>
@endsection
