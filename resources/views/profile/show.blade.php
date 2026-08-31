@extends('layouts.app', ['title' => 'My Profile'])

@section('content')
<x-page-toolbar title="My Profile" subtitle="Your account details and sign-in security." />

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <x-avatar :user="$user" :size="96" class="mx-auto mb-3" />
                <h2 class="h5 mb-1">{{ $user->name }}</h2>
                <div class="cell-sub mb-2">{{ $user->email }}</div>
                <div class="d-flex gap-2 justify-content-center">
                    <span class="badge-soft {{ $user->roleBadgeClass() }}">{{ $user->roleLabel() }}</span>
                    <span class="badge-soft {{ $user->is_active ? 'b-ok' : 'b-neutral' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        {{-- Profile details + avatar --}}
        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-loading>
            @csrf @method('PUT')
            <div class="form-section">
                <div class="fs-head"><span class="fs-ico"><x-icon name="name" /></span><div><div class="fs-t">Profile</div><div class="fs-s">Your name, email and photo.</div></div></div>
                <div class="fs-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                        <div class="col-12">
                            <label class="form-label">Profile Photo</label>
                            <div class="d-flex align-items-center gap-3">
                                <x-avatar :user="$user" :size="56" />
                                <div class="flex-grow-1">
                                    <input class="form-control" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text">JPG, PNG or WebP, up to 2&nbsp;MB. Leave empty to keep your current photo.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end align-items-center">
                @if ($user->avatar_path)
                    <span class="me-auto">
                        {{-- Nested-form-safe: this button posts the separate remove form below. --}}
                        <button class="btn btn-outline-danger" type="submit" form="remove-avatar-form" data-no-loading><x-icon name="trash" :size="16" /> Remove Photo</button>
                    </span>
                @endif
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Save Profile</button>
            </div>
        </form>

        @if ($user->avatar_path)
            <form id="remove-avatar-form" method="post" action="{{ route('profile.avatar.destroy') }}"
                  class="d-none" data-confirm="Remove your profile photo?">
                @csrf @method('DELETE')
            </form>
        @endif

        {{-- Change password --}}
        <form method="post" action="{{ route('profile.password') }}" class="mt-4" data-loading>
            @csrf @method('PUT')
            <div class="form-section">
                <div class="fs-head"><span class="fs-ico"><x-icon name="settings" /></span><div><div class="fs-t">Password</div><div class="fs-s">Change your sign-in password.</div></div></div>
                <div class="fs-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Current Password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div>
                        <div class="col-md-4"><label class="form-label">New Password</label><input class="form-control" type="password" name="password" autocomplete="new-password" required></div>
                        <div class="col-md-4"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="password_confirmation" autocomplete="new-password" required></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn btn-outline-primary" type="submit"><x-icon name="check" :size="16" /> Update Password</button>
            </div>
        </form>
    </div>
</div>
@endsection
