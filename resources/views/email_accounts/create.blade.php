@extends('layouts.app', ['title' => 'Add Email Account'])

@section('content')
<x-page-toolbar title="Add Email Account">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('email-accounts.index') }}"><x-icon name="chevron-left" :size="15" /> All email accounts</a>
</x-page-toolbar>

<form method="post" action="{{ route('email-accounts.store') }}" data-loading>@include('email_accounts._form')</form>
@endsection
