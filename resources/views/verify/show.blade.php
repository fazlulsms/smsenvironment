@php $found = $found ?? false; @endphp
@extends('public.layouts.site')
@section('title', 'Document Verification'.($found ? ' · '.$data['reference'] : ''))
@section('meta_description', 'Verification result for a document issued by SMS Environmental Alliance.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section" style="padding-top:44px">
    <div class="wrap verify-wrap2">
        @if (! $found)
            <div class="verify-card">
                <div class="verify-status bad"><span class="vi">✕</span><div><h2>Document not found</h2><p>We could not verify a document using the information provided.</p></div></div>
                <div class="verify-body">
                    <p style="color:var(--muted);margin-top:0">The verification reference <span class="vid">{{ $code ?? '' }}</span> did not match any document we have issued. Please check the reference, or contact SMS Environmental Alliance.</p>
                    <div class="verify-search">@include('verify._search')</div>
                </div>
            </div>
        @else
            @php
                $money = fn ($v) => number_format((float) $v, 2);
                $isAddVat = ($data['vat_treatment'] ?? '') === 'add' && (float) ($data['vat_amount'] ?? 0) > 0;
                $vatRate = rtrim(rtrim(number_format((float) ($data['vat_rate'] ?? 0), 3, '.', ''), '0'), '.');
            @endphp
            <div class="verify-card">
                @if ($verified)
                    <div class="verify-status ok"><span class="vi">✓</span><div><h2>Verified — authentic document</h2><p>This {{ strtolower($typeLabel) }} was issued by {{ $entityName }} and has not been altered.</p></div></div>
                @else
                    <div class="verify-status warn"><span class="vi">!</span><div><h2>On record — could not confirm integrity</h2><p>This reference exists in our records, but its details could not be confirmed. Please contact us.</p></div></div>
                @endif
                <div class="verify-body">
                    <dl class="verify-kv">
                        <dt>Document</dt><dd>{{ $typeLabel }}</dd>
                        <dt>Document Number</dt><dd>{{ $data['reference'] }}</dd>
                        <dt>Issue Date</dt><dd>{{ $data['date'] }}</dd>
                        <dt>Issued By</dt><dd>{{ $entityName }}</dd>
                        <dt>Client / Organization</dt><dd>{{ $data['client'] ?: '—' }}</dd>
                    </dl>

                    <h3 style="font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin:22px 0 4px">Service(s)</h3>
                    @foreach ($data['services'] as $svc)
                        <div class="verify-svc">
                            <div class="verify-svc-top"><span>{{ $svc['name'] }}</span><span>{{ $data['currency'] }} {{ $money($svc['amount']) }}</span></div>
                            @if (! empty($svc['includes']))
                                <ul>@foreach ($svc['includes'] as $inc)<li>{{ $inc }}</li>@endforeach</ul>
                            @endif
                        </div>
                    @endforeach

                    <div class="verify-totals">
                        <div class="rl"><span>Net Amount</span><span>{{ $data['currency'] }} {{ $money($data['net_amount']) }}</span></div>
                        @if ($isAddVat)
                            <div class="rl"><span>VAT @ {{ $vatRate }}%</span><span>{{ $data['currency'] }} {{ $money($data['vat_amount']) }}</span></div>
                        @endif
                        <div class="rl grand"><span>Total Amount</span><span>{{ $data['currency'] }} {{ $money($data['total_amount']) }}</span></div>
                    </div>

                    <dl class="verify-kv" style="margin-top:18px">
                        <dt>Verification Reference</dt><dd class="vid">{{ $document->verification_id }}</dd>
                    </dl>
                </div>
            </div>
        @endif
        <p class="verify-note">This page reflects SMS Environmental Alliance's official record for this document. For any discrepancy, contact the issuer directly.</p>
    </div>
</section>
@endsection
