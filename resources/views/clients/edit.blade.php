@extends('layouts.app', ['title' => 'Edit '.$client->company_name])

@section('content')
<x-page-toolbar title="Edit Client" subtitle="{{ $client->company_name }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('clients.show', $client) }}"><x-icon name="chevron-left" :size="15" /> Back to client</a>
</x-page-toolbar>

<form method="post" action="{{ route('clients.update', $client) }}" data-loading>
    @method('put')
    @include('clients._form')
</form>
@endsection
