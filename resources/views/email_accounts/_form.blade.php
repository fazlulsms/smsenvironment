@csrf
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="email" /></span><div><div class="fs-t">Sender Identity</div><div class="fs-s">Shown as the From address on this entity's emails.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Label</label><input class="form-control" name="label" value="{{ old('label', $account->label) }}" placeholder="e.g. Primary"></div>
            <div class="col-md-4"><label class="form-label">From Name</label><input class="form-control" name="from_name" value="{{ old('from_name', $account->from_name) }}"></div>
            <div class="col-md-4"><label class="form-label">From Address</label><input class="form-control" type="email" name="from_address" value="{{ old('from_address', $account->from_address) }}"></div>
            <div class="col-md-6"><label class="form-label">Reply-To (optional)</label><input class="form-control" type="email" name="reply_to" value="{{ old('reply_to', $account->reply_to) }}"></div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="settings" /></span><div><div class="fs-t">SMTP Connection</div><div class="fs-s">Credentials are encrypted at rest and never shown again.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Mailer</label>
                <select class="form-select" name="mailer_type">
                    @foreach (['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'log' => 'Log (testing)'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('mailer_type', $account->mailer_type) === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><label class="form-label">Host</label><input class="form-control" name="host" value="{{ old('host', $account->host) }}" placeholder="smtp.example.com"></div>
            <div class="col-md-3"><label class="form-label">Port</label><input class="form-control" type="number" name="port" value="{{ old('port', $account->port) }}" placeholder="587"></div>
            <div class="col-md-4"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username', $account->username) }}" autocomplete="off"></div>
            <div class="col-md-4">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" autocomplete="new-password" placeholder="{{ $account->exists ? '•••••••• (unchanged)' : '' }}">
                @if ($account->exists)<div class="form-hint">Leave blank to keep the current password.</div>@endif
            </div>
            <div class="col-md-4">
                <label class="form-label">Encryption</label>
                <select class="form-select" name="encryption">
                    @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('encryption', $account->encryption ?: 'none') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', $account->active ?? true))> <span class="form-check-label">Active</span></label></div>
            <div class="col-md-6"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', $account->is_default ?? false))> <span class="form-check-label">Default sender for this entity</span></label></div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('email-accounts.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $account->exists ? 'Update' : 'Save' }} Account</button>
</div>
