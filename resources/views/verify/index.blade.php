<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Document Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand:#1f6f4a; --muted:#667085; --line:#e4ebe7; }
        body { background:#f4f6f5; color:#1f2933; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .verify-wrap { max-width:560px; margin:0 auto; padding:48px 16px; }
        .brandbar { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
        .brandbar .dot { width:34px; height:34px; border-radius:8px; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .brandbar b { font-size:1.05rem; }
        .brandbar small { color:var(--muted); display:block; line-height:1; }
        .vcard { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .foot { color:var(--muted); font-size:.8rem; text-align:center; margin-top:18px; }
    </style>
</head>
<body>
<div class="verify-wrap">
    <div class="brandbar">
        <span class="dot">SM</span>
        <span><b>SMS Environmental Alliance</b><small>Document verification</small></span>
    </div>
    <div class="vcard">
        <h1 class="h5 mb-1">Verify a document</h1>
        <p class="text-secondary small mb-3">Scan the QR code on a quotation or proforma invoice, or enter its document number below to confirm it is genuine.</p>
        @include('verify._search')
    </div>
    <p class="foot">Only official {{ 'SMS Environmental Alliance' }} documents can be verified here.</p>
</div>
</body>
</html>
