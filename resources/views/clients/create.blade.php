@extends('layouts.app', ['title' => 'Add Client'])

@section('content')
<h1 class="h3 mb-3">Add Client</h1>
<form class="panel p-3" method="post" action="{{ route('clients.store') }}">
    @include('clients._form')
</form>
@endsection
