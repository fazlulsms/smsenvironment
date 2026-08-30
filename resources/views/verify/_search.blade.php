<form method="get" action="{{ route('verify.index') }}" class="verify-search">
    <label style="display:block;font-weight:600;margin-bottom:.4rem;color:var(--ink)">Verify by document number</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <input name="q" value="{{ $query ?? '' }}" placeholder="e.g. SMSEA/PI/2026/0022" required
            style="flex:1;min-width:220px;padding:.72rem .85rem;border:1px solid var(--line);border-radius:10px;font:inherit;color:var(--ink)">
        <button class="btn2 btn2--primary" type="submit">Verify Document</button>
    </div>
    @if (! empty($notFound))
        <div style="color:#b42318;font-size:.85rem;margin-top:.5rem">No document found for that number.</div>
    @endif
</form>
