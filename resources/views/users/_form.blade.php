@csrf
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="clients" /></span><div><div class="fs-t">Account</div><div class="fs-s">Name, sign-in email and access level.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select class="form-select" name="role" required>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role ?? \App\Models\User::ROLE_STAFF) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">
                    <b>Staff</b> — daily work (clients, quotations, invoices). <b>Admin</b> — plus catalogue, banks &amp; email history.
                    <b>Super Admin</b> — full control incl. users, settings &amp; deletion.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="settings" /></span><div><div class="fs-t">Password</div><div class="fs-s">{{ $user->exists ? 'Leave blank to keep the current password.' : 'Set an initial password — the user can change it later.' }}</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ $user->exists ? 'New Password' : 'Password' }}</label>
                <input class="form-control" type="password" name="password" autocomplete="new-password" @unless($user->exists) required @endunless>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input class="form-control" type="password" name="password_confirmation" autocomplete="new-password" @unless($user->exists) required @endunless>
            </div>
        </div>
    </div>
</div>

@if ($user->exists && $user->isSuperAdmin())
    <div class="form-section">
        <div class="fs-body">
            <label class="form-check">
                <input class="form-check-input" type="checkbox" name="confirm_downgrade" value="1">
                <span class="form-check-label">I understand — allow removing Super Admin access from this account if I change the role above.</span>
            </label>
            @if (!empty($isLastActiveSuperAdmin) && $isLastActiveSuperAdmin)
                <div class="form-text text-danger mt-2">This is the last active Super Admin and cannot be downgraded or deactivated.</div>
            @endif
        </div>
    </div>
@endif

<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $user->exists ? 'Update' : 'Create' }} User</button>
</div>
