@extends('public.layouts.site')
@section('title', 'Verify SMSEA Quotation or Invoice')
@section('meta_description', 'Verify the authenticity of a quotation or proforma invoice issued by SMS Environmental Alliance using its verification reference or document number.')

@include('public.partials.breadcrumbs', ['label' => 'Verify Document', 'url' => route('verify.index')])

@section('content')
<section class="hero">
    <div class="wrap hero-inner" style="padding:64px 0 40px">
        <span class="eyebrow">Document Verification</span>
        <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Verify a Document</h1>
        <p>Verify the authenticity of a quotation or proforma invoice issued by SMS Environmental Alliance.</p>
    </div>
</section>

<section class="section">
    <div class="wrap verify-wrap2">
        <div class="verify-card">
            <div class="verify-body">
                <p class="text-muted" style="color:var(--muted);margin-top:0">Scan the QR code on your document, or enter its document number below. You can verify a document you already have — this is not a public document directory.</p>
                @include('verify._search')
            </div>
        </div>
        <p class="verify-note">Only official SMS Environmental Alliance documents can be verified here.</p>
    </div>
</section>
@endsection
