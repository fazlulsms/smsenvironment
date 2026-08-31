@extends('layouts.app', ['title' => 'Users'])

@section('content')
<x-page-toolbar title="Users" subtitle="Manage who can access SMSEA Office and what they can do.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('users.create') }}"><x-icon name="plus" :size="16" /> Add User</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($users as $u)
                @php
                    $isSelf = $u->id === auth()->id();
                    $isLastSuper = $u->isSuperAdmin() && $u->is_active && $activeSuperAdmins <= 1;
                @endphp
                <tr>
                    <td>
                        <span class="d-inline-flex align-items-center gap-2">
                            <x-avatar :user="$u" :size="30" />
                            <span>
                                <a class="row-title" href="{{ route('users.edit', $u) }}">{{ $u->name }}</a>
                                @if ($isSelf)<span class="cell-sub">You</span>@endif
                            </span>
                        </span>
                    </td>
                    <td class="cell-sub">{{ $u->email }}</td>
                    <td><span class="badge-soft {{ $u->roleBadgeClass() }}">{{ $u->roleLabel() }}</span></td>
                    <td>
                        @if ($u->is_active)
                            <span class="badge-soft b-ok">Active</span>
                        @else
                            <span class="badge-soft b-neutral">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ route('users.edit', $u) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                            <div class="dropdown">
                                <button class="btn-icon" type="button" data-bs-toggle="dropdown" title="More"><x-icon name="dots" :size="16" /></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item" href="{{ route('users.edit', $u) }}"><x-icon name="edit" :size="15" class="me-2" />Edit</a></li>
                                    @unless ($isLastSuper)
                                        <li>
                                            <form method="post" action="{{ route('users.active', $u) }}" data-confirm="{{ $u->is_active ? 'Deactivate this user? They will be signed out and cannot log in.' : 'Reactivate this user?' }}">
                                                @csrf @method('PATCH')
                                                <button class="dropdown-item" type="submit">
                                                    <x-icon name="{{ $u->is_active ? 'x' : 'check' }}" :size="15" class="me-2" />{{ $u->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        </li>
                                    @endunless
                                    @unless ($isSelf || $isLastSuper)
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="post" action="{{ route('users.destroy', $u) }}" data-confirm="Delete this user? Deactivate instead if they have created records.">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger" type="submit"><x-icon name="trash" :size="15" class="me-2" />Delete</button>
                                            </form>
                                        </li>
                                    @endunless
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">
                    <x-empty-state icon="clients" title="No users yet" message="Add your first team member.">
                        <a class="btn btn-primary btn-sm" href="{{ route('users.create') }}"><x-icon name="plus" :size="15" /> Add User</a>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($users->hasPages())
    <div class="mt-3">{{ $users->links() }}</div>
@endif
@endsection
