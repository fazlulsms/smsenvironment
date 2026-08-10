@extends('layouts.app', ['title' => 'Bank Accounts'])

@section('content')
<x-page-toolbar title="Bank Accounts" subtitle="Only the selected bank appears on each document. One account is the default.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('bank-accounts.create') }}"><x-icon name="plus" :size="16" /> Add Bank Account</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Bank</th><th>Beneficiary</th><th>Account No.</th><th>SWIFT</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($bankAccounts as $bankAccount)
                <tr>
                    <td>
                        <span class="row-title">{{ $bankAccount->bank_name }}</span>
                        @if ($bankAccount->branch)<div class="cell-sub">{{ $bankAccount->branch }}</div>@endif
                    </td>
                    <td>{{ $bankAccount->beneficiary_name }}</td>
                    <td class="cell-sub">{{ $bankAccount->account_number }}</td>
                    <td class="cell-sub">{{ $bankAccount->swift_code ?: '—' }}</td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if ($bankAccount->is_active)
                                <span class="badge-soft b-ok"><span class="dotmark"></span>Active</span>
                            @else
                                <span class="badge-soft b-neutral"><span class="dotmark"></span>Inactive</span>
                            @endif
                            @if ($bankAccount->is_default)<span class="badge-soft b-info"><x-icon name="check" :size="12" />Default</span>@endif
                        </div>
                    </td>
                    <td>
                        <div class="row-actions">
                            @unless ($bankAccount->is_default)
                                <form method="post" action="{{ route('bank-accounts.default', $bankAccount) }}">@csrf
                                    <button class="btn btn-outline-secondary btn-sm" type="submit" title="Make this the default bank">Set Default</button>
                                </form>
                            @endunless
                            <a class="btn-icon" href="{{ route('bank-accounts.edit', $bankAccount) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">
                    <x-empty-state icon="bank" title="No bank accounts yet"
                        message="Add the bank account that should appear on your quotations and invoices.">
                        <a class="btn btn-primary btn-sm" href="{{ route('bank-accounts.create') }}"><x-icon name="plus" :size="15" /> Add Bank Account</a>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($bankAccounts->hasPages())
    <div class="mt-3">{{ $bankAccounts->links() }}</div>
@endif
@endsection
