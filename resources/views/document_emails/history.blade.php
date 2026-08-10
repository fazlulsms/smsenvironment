<div class="card table-card mt-3">
    <div class="card-head">
        <h2>Email History</h2>
        @if ($deliveries->firstWhere('status', 'sent'))
            <span class="card-link">Last sent {{ $deliveries->firstWhere('status', 'sent')->sent_at?->format('d M Y, g:i A') }}</span>
        @endif
    </div>
    @if ($deliveries->isEmpty())
        <div class="card-body text-secondary small">No emails sent yet.</div>
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>When</th><th>To</th><th>CC</th><th>Sent by</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($deliveries as $delivery)
                    <tr>
                        <td class="cell-sub">{{ ($delivery->sent_at ?? $delivery->created_at)?->format('d M Y, g:i A') }}</td>
                        <td>{{ $delivery->to_email }}</td>
                        <td class="cell-sub">{{ $delivery->cc_emails ? implode(', ', $delivery->cc_emails) : '—' }}</td>
                        <td class="cell-sub">{{ $delivery->sender?->name ?? 'System' }}</td>
                        <td>
                            @if ($delivery->status === 'sent')
                                <span class="badge-soft b-ok"><x-icon name="check" :size="12" />Sent</span>
                            @else
                                <span class="badge-soft b-danger" title="{{ $delivery->error_summary }}"><x-icon name="alert" :size="12" />Failed</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
