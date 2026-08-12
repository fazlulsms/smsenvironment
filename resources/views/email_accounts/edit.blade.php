@extends('layouts.app', ['title' => 'Edit Email Account'])

@section('content')
<x-page-toolbar title="Edit Email Account" subtitle="{{ $account->label ?: $account->from_address }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('email-accounts.index') }}"><x-icon name="chevron-left" :size="15" /> All email accounts</a>
</x-page-toolbar>

<form method="post" action="{{ route('email-accounts.update', $account) }}" data-loading>@method('put')@include('email_accounts._form')</form>
@endsection
