@extends('layouts.app', ['title' => 'Edit Client'])

@section('content')
<h1 class="h3 mb-3">Edit Client</h1>
<form class="panel p-3" method="post" action="{{ route('clients.update', $client) }}">
    @method('put')
    @include('clients._form')
</form>
@endsection
