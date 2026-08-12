@extends('layouts.app', ['title' => 'Email Accounts'])

@section('content')
<x-page-toolbar title="Email Accounts" subtitle="Outgoing sender identity for {{ $entity?->name }}. Used automatically when sending this entity's documents.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('email-accounts.create') }}"><x-icon name="plus" :size="16" /> Add Email Account</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Label</th><th>From</th><th>Host</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td><span class="row-title">{{ $account->label ?: '—' }}</span><div class="cell-sub">{{ ucfirst($account->mailer_type) }}</div></td>
                    <td>{{ $account->from_name ? $account->from_name.' · ' : '' }}{{ $account->from_address ?: '—' }}</td>
                    <td class="cell-sub">{{ $account->host ? $account->host.':'.$account->port : '—' }}</td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if ($account->active)<span class="badge-soft b-ok"><span class="dotmark"></span>Active</span>@else<span class="badge-soft b-neutral"><span class="dotmark"></span>Inactive</span>@endif
                            @if ($account->is_default)<span class="badge-soft b-info"><x-icon name="check" :size="12" />Default</span>@endif
                            @if (! $account->mailerConfig())<span class="badge-soft b-warn" title="Host + From address required">Incomplete</span>@endif
                        </div>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ route('email-accounts.edit', $account) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                            <form method="post" action="{{ route('email-accounts.destroy', $account) }}" onsubmit="return confirm('Delete this email account?')">
                                @csrf @method('delete')
                                <button class="btn-icon" type="submit" title="Delete"><x-icon name="trash" :size="16" /></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">
                    <x-empty-state icon="email" title="No email account configured"
                        message="Add an SMTP account so this entity's quotations and invoices send from its own address. Until then, the application default mailer is used.">
                        <a class="btn btn-primary btn-sm" href="{{ route('email-accounts.create') }}"><x-icon name="plus" :size="15" /> Add Email Account</a>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
