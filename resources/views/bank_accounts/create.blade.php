@extends('layouts.app', ['title' => 'Add Bank Account'])

@section('content')
<h1 class="h3 mb-3">Add Bank Account</h1>
<form class="panel p-3" method="post" action="{{ route('bank-accounts.store') }}">@include('bank_accounts._form')</form>
@endsection
