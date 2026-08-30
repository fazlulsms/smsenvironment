@extends('layouts.app', ['title' => 'Add User'])

@section('content')
<x-page-toolbar title="Add User">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('users.index') }}"><x-icon name="chevron-left" :size="15" /> All users</a>
</x-page-toolbar>

@php $user = new \App\Models\User; @endphp
<form method="post" action="{{ route('users.store') }}" data-loading>@include('users._form')</form>
@endsection
