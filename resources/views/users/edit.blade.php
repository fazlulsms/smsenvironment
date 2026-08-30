@extends('layouts.app', ['title' => 'Edit User'])

@section('content')
<x-page-toolbar title="Edit User" subtitle="{{ $user->email }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('users.index') }}"><x-icon name="chevron-left" :size="15" /> All users</a>
</x-page-toolbar>

<form method="post" action="{{ route('users.update', $user) }}" data-loading>
    @method('PUT')
    @include('users._form')
</form>
@endsection
