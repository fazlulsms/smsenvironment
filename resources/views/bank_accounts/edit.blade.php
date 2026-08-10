@extends('layouts.app', ['title' => 'Edit Bank Account'])

@section('content')
<x-page-toolbar title="Edit Bank Account" subtitle="{{ $bankAccount->bank_name }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('bank-accounts.index') }}"><x-icon name="chevron-left" :size="15" /> All bank accounts</a>
</x-page-toolbar>

<form method="post" action="{{ route('bank-accounts.update', $bankAccount) }}" data-loading>@method('put') @include('bank_accounts._form')</form>
@endsection
