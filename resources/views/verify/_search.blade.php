<form method="get" action="{{ route('verify.index') }}" class="mt-2">
    <label class="form-label fw-semibold mb-1">Verify by document number</label>
    <div class="input-group">
        <input class="form-control" name="q" value="{{ $query ?? '' }}" placeholder="e.g. SMSEA/PI/2026/0022" required>
        <button class="btn btn-success" type="submit">Verify</button>
    </div>
    @if (! empty($notFound))
        <div class="text-danger small mt-2">No document found for that number.</div>
    @endif
</form>
