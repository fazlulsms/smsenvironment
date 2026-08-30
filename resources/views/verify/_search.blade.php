<form method="get" action="{{ route('verify.index') }}" class="verify-search">
    <label style="display:block;font-weight:600;margin-bottom:.3rem;color:var(--ink)">Enter the document number shown on your quotation or proforma invoice.</label>
    <div style="color:var(--muted);font-size:.85rem;margin-bottom:.6rem">Example: SMSEA/PI/2026/0022</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <input name="q" value="{{ $query ?? '' }}" placeholder="SMSEA/PI/2026/0022" required
            style="flex:1;min-width:220px;padding:.8rem .9rem;border:1px solid var(--line);border-radius:10px;font:inherit;color:var(--ink)">
        <button class="btn2 btn2--primary" type="submit">Verify Document</button>
    </div>
    @if (! empty($notFound))
        <div style="color:#b42318;font-size:.85rem;margin-top:.5rem">No document found for that number.</div>
    @endif
</form>
