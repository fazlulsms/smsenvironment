@extends('public.layouts.site')
@section('title', 'Environmental, Chemical & Sustainability Services')
@section('meta_description', 'Environmental assessment and testing, chemical management, sustainability services and training in Bangladesh — EIA, environmental parameter testing, energy audit, GHG inventory, wastewater/ETP and more.')

@include('public.partials.breadcrumbs', ['label' => 'Services', 'url' => route('public.services')])

@section('content')
@php $heroImg = is_file(public_path('images/site/services-monitoring.webp')); @endphp
<section class="hero">
    <div class="wrap {{ $heroImg ? 'hero-grid' : 'hero-inner' }}" @unless ($heroImg) style="padding-top:64px;padding-bottom:44px" @endunless>
        <div class="{{ $heroImg ? 'hero-copy' : '' }}">
            <span class="eyebrow">Our Services</span>
            <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Environmental, Chemical &amp; Sustainability Services</h1>
            <p>Focused technical services for industrial facilities — environmental assessment and testing, chemical management, sustainability improvement, and capacity-building training.</p>
            <div class="hero-cta">
                <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request a Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
            </div>
        </div>
        @if ($heroImg)
            <div class="hero-media reveal">
                <img src="{{ asset('images/site/services-monitoring.webp') }}?v={{ @filemtime(public_path('images/site/services-monitoring.webp')) }}" alt="Effluent treatment plant at an industrial facility" width="1280" height="960" loading="eager">
            </div>
        @endif
    </div>
</section>

<section class="section">
    <div class="wrap">
        @foreach ($families as $family)
            <div class="family" id="{{ $family['key'] }}">
                <div class="family-head">
                    <span class="ico-wrap">@include('public.partials.icon', ['name' => $family['icon']])</span>
                    <div>
                        <h2>{{ $family['title'] }}</h2>
                        <p>{{ $family['tagline'] }}</p>
                    </div>
                </div>
                <div class="service-cols">
                    @foreach ($family['services'] as $service)
                        <div class="item">@include('public.partials.icon', ['name' => 'check']) <span>{{ $service }}</span></div>
                    @endforeach
                </div>
                <div style="margin-top:20px">
                    <a class="btn2 btn2--outline" href="{{ route('public.contact') }}#proposal">Request a Proposal for {{ $family['title'] }}</a>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="section section--brand">
    <div class="wrap" style="text-align:center">
        <h2>Not sure which service you need?</h2>
        <p style="color:#cfe6da;max-width:620px;margin:.5rem auto 1.4rem">Tell us about your facility and objective — we’ll recommend the right environmental, chemical or sustainability service.</p>
        <a class="btn2 btn2--ghost" href="{{ route('public.contact') }}#proposal">Request a Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
    </div>
</section>
@endsection
