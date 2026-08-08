@extends('layouts.app', ['title' => 'Bank Accounts'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Bank Accounts</h1>
    <a class="btn btn-primary" href="{{ route('bank-accounts.create') }}">Add Bank Account</a>
</div>
<div class="panel">
    <table class="table align-middle mb-0">
        <thead><tr><th>Bank</th><th>Beneficiary</th><th>Account</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($bankAccounts as $bankAccount)
            <tr>
                <td>{{ $bankAccount->bank_name }}<div class="text-secondary small">{{ $bankAccount->branch }}</div></td>
                <td>{{ $bankAccount->beneficiary_name }}</td>
                <td>{{ $bankAccount->account_number }}</td>
                <td>
                    {{ $bankAccount->is_active ? 'Active' : 'Inactive' }}
                    @if($bankAccount->is_default)<div class="text-success small">Default</div>@endif
                </td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('bank-accounts.edit', $bankAccount) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $bankAccounts->links() }}</div>
@endsection
