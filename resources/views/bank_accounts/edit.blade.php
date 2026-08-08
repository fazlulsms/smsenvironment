@extends('layouts.app', ['title' => 'Edit Bank Account'])

@section('content')
<h1 class="h3 mb-3">Edit Bank Account</h1>
<form class="panel p-3" method="post" action="{{ route('bank-accounts.update', $bankAccount) }}">@method('put') @include('bank_accounts._form')</form>
@endsection
