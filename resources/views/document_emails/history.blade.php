<div class="panel p-3 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Email History</h2>
        @if ($deliveries->firstWhere('status', 'sent'))
            <div class="text-secondary small">Last emailed: {{ $deliveries->firstWhere('status', 'sent')->sent_at?->format('d M Y, g:i A') }}</div>
        @endif
    </div>
    @forelse ($deliveries as $delivery)
        <div class="border-top py-2 small">
            <div><strong>{{ $delivery->sent_at?->format('d M Y, g:i A') ?? $delivery->created_at?->format('d M Y, g:i A') }}</strong></div>
            <div>Sent to: {{ $delivery->to_email }}</div>
            @if ($delivery->cc_emails)
                <div>CC: {{ implode(', ', $delivery->cc_emails) }}</div>
            @endif
            <div>Sent by: {{ $delivery->sender?->name ?? 'System' }}</div>
            <div>Status: {{ ucfirst($delivery->status) }}</div>
        </div>
    @empty
        <div class="text-secondary small border-top pt-2">No emails sent yet.</div>
    @endforelse
</div>
