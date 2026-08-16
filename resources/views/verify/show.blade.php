@php $found = $found ?? false; @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Document Verification{{ $found ? ' · '.$data['reference'] : '' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand:#1f6f4a; --brand-dark:#175639; --ink:#1f2933; --muted:#667085; --line:#e4ebe7; }
        body { background:#f4f6f5; color:var(--ink); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .verify-wrap { max-width:680px; margin:0 auto; padding:24px 16px 48px; }
        .brandbar { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
        .brandbar .dot { width:34px; height:34px; border-radius:8px; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .brandbar b { font-size:1.05rem; }
        .brandbar small { color:var(--muted); display:block; line-height:1; }
        .vcard { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .status { padding:18px 20px; display:flex; align-items:center; gap:12px; color:#fff; }
        .status .ico { font-size:1.6rem; line-height:1; }
        .status.ok { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); }
        .status.warn { background:linear-gradient(135deg,#b7791f,#8a5a12); }
        .status.bad { background:linear-gradient(135deg,#b42318,#8a1c14); }
        .status h1 { font-size:1.1rem; margin:0; font-weight:700; }
        .status p { margin:0; opacity:.9; font-size:.85rem; }
        .vbody { padding:20px; }
        .kv { display:grid; grid-template-columns:150px 1fr; gap:6px 14px; font-size:.94rem; }
        .kv dt { color:var(--muted); font-weight:500; }
        .kv dd { margin:0; font-weight:600; }
        .svc { border:1px solid var(--line); border-radius:10px; padding:12px 14px; margin-top:10px; }
        .svc-top { display:flex; justify-content:space-between; gap:12px; font-weight:600; }
        .svc ul { margin:.4rem 0 0; padding-left:1.1rem; color:var(--muted); font-size:.85rem; }
        .totals { margin-top:16px; border-top:2px solid var(--line); padding-top:12px; }
        .totals .row-line { display:flex; justify-content:space-between; padding:3px 0; font-size:.94rem; }
        .totals .grand { font-weight:800; font-size:1.05rem; color:var(--brand-dark); border-top:1px solid var(--line); margin-top:6px; padding-top:8px; }
        .foot { color:var(--muted); font-size:.8rem; text-align:center; margin-top:18px; }
        .vid { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.5px; }
    </style>
</head>
<body>
<div class="verify-wrap">
    <div class="brandbar">
        <span class="dot">SM</span>
        <span><b>{{ $found ? $entityName : 'SMS Environmental Alliance' }}</b><small>Document verification</small></span>
    </div>

    @if (! $found)
        <div class="vcard">
            <div class="status bad"><span class="ico">✕</span><div><h1>Document not found</h1><p>No document matches this code.</p></div></div>
            <div class="vbody">
                <p class="text-secondary">The verification code <span class="vid">{{ $code ?? '' }}</span> did not match any issued document. Check the code, or search by document number below.</p>
                @include('verify._search')
            </div>
        </div>
    @else
        @php
            $money = fn ($v) => number_format((float) $v, 2);
            $isAddVat = ($data['vat_treatment'] ?? '') === 'add' && (float) ($data['vat_amount'] ?? 0) > 0;
            $vatRate = rtrim(rtrim(number_format((float) ($data['vat_rate'] ?? 0), 3, '.', ''), '0'), '.');
        @endphp
        <div class="vcard">
            @if ($verified)
                <div class="status ok"><span class="ico">✓</span><div><h1>Verified — authentic document</h1><p>This {{ strtolower($typeLabel) }} was issued by {{ $entityName }} and has not been altered.</p></div></div>
            @else
                <div class="status warn"><span class="ico">!</span><div><h1>On record — could not confirm integrity</h1><p>This reference exists in our records, but its signature did not match. Please contact us to confirm.</p></div></div>
            @endif
            <div class="vbody">
                <dl class="kv">
                    <dt>Document</dt><dd>{{ $typeLabel }}</dd>
                    <dt>Reference No.</dt><dd>{{ $data['reference'] }}</dd>
                    <dt>Date</dt><dd>{{ $data['date'] }}</dd>
                    <dt>Issued by</dt><dd>{{ $entityName }}</dd>
                    <dt>Client</dt><dd>{{ $data['client'] ?: '—' }}</dd>
                </dl>

                <h2 class="h6 text-uppercase text-secondary mt-4 mb-1" style="letter-spacing:.5px;font-size:.75rem;">Service(s)</h2>
                @foreach ($data['services'] as $svc)
                    <div class="svc">
                        <div class="svc-top"><span>{{ $svc['name'] }}</span><span>{{ $data['currency'] }} {{ $money($svc['amount']) }}</span></div>
                        @if (! empty($svc['includes']))
                            <ul>@foreach ($svc['includes'] as $inc)<li>{{ $inc }}</li>@endforeach</ul>
                        @endif
                    </div>
                @endforeach

                <div class="totals">
                    <div class="row-line"><span>Net Amount</span><span>{{ $data['currency'] }} {{ $money($data['net_amount']) }}</span></div>
                    @if ($isAddVat)
                        <div class="row-line"><span>VAT @ {{ $vatRate }}%</span><span>{{ $data['currency'] }} {{ $money($data['vat_amount']) }}</span></div>
                    @else
                        <div class="row-line"><span>VAT Treatment</span><span>{{ ucwords(str_replace('_', ' ', $data['vat_treatment'])) }}</span></div>
                    @endif
                    <div class="row-line grand"><span>Total Payable</span><span>{{ $data['currency'] }} {{ $money($data['total_amount']) }}</span></div>
                </div>

                <dl class="kv mt-4">
                    <dt>Verification ID</dt><dd class="vid">{{ $document->verification_id }}</dd>
                </dl>

                <hr class="my-4">
                @include('verify._search')
            </div>
        </div>
    @endif

    <p class="foot">This page reflects {{ $found ? $entityName : 'SMS Environmental Alliance' }}'s official record for this document. For any discrepancy, contact the issuer directly.</p>
</div>
</body>
</html>
