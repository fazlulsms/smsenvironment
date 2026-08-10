@extends('layouts.app', ['title' => 'Add Bank Account'])

@section('content')
<x-page-toolbar title="Add Bank Account">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('bank-accounts.index') }}"><x-icon name="chevron-left" :size="15" /> All bank accounts</a>
</x-page-toolbar>

<form method="post" action="{{ route('bank-accounts.store') }}" data-loading>@include('bank_accounts._form')</form>
@endsection
