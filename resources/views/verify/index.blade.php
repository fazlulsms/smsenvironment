@extends('public.layouts.site')
@section('title', 'Verify SMSEA Documents')
@section('meta_description', 'Confirm the authenticity of documents issued by SMS Environmental Alliance — verify a proforma invoice or quotation now, with report verification coming soon.')

@include('public.partials.breadcrumbs', ['label' => 'Verify', 'url' => route('verify.index')])

@section('content')
<section class="hero">
    <div class="wrap hero-inner" style="padding:64px 0 40px">
        <span class="eyebrow">SMSEA Verification Center</span>
        <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Verify Documents</h1>
        <p>Confirm the authenticity of documents issued by SMS Environmental Alliance.</p>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="grid grid-2 verify-center">
            {{-- CARD 1 — active: existing commercial-document verification --}}
            <div class="verify-option">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'clipboard'])</span>
                <h2>Invoice &amp; Quotation</h2>
                <p>Verify a proforma invoice or quotation issued by SMS Environmental Alliance.</p>
                <a class="btn2 btn2--primary" href="#invoice-quotation">Verify Invoice / Quotation</a>
            </div>

            {{-- CARD 2 — reports: coming soon (no functionality yet) --}}
            <div class="verify-option soon">
                <span class="soon-badge">Coming Soon</span>
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'leaf'])</span>
                <h2>Reports</h2>
                <p>Verify environmental, technical and assessment reports issued by SMS Environmental Alliance.</p>
                <span class="btn2 btn2--outline" aria-disabled="true" style="opacity:.6;pointer-events:none">Coming Soon</span>
            </div>
        </div>
    </div>
</section>

{{-- Existing Invoice/Quotation verification workflow (unchanged engine) --}}
<section class="section section--soft" id="invoice-quotation">
    <div class="wrap verify-wrap2">
        <div class="section-head">
            <span class="eyebrow">Invoice &amp; Quotation</span>
            <h2>Verify a proforma invoice or quotation</h2>
        </div>
        <div class="verify-card">
            <div class="verify-body">
                <p style="color:var(--muted);margin-top:0">Scan the QR code on your document, or enter its document number below. You can verify a document you already have — this is not a public document directory.</p>
                @include('verify._search')
            </div>
        </div>
    </div>
</section>

{{-- Trust statement --}}
<section class="section">
    <div class="wrap" style="max-width:760px;text-align:center">
        <span class="eyebrow">Document Authenticity</span>
        <h2>Confidence in every document</h2>
        <p class="lead">SMSEA verification helps clients and stakeholders confirm that a document was genuinely issued by SMS Environmental Alliance. Use the verification reference provided on the document or scan its QR code.</p>
    </div>
</section>
@endsection
