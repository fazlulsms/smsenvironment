@extends('public.layouts.site')
@section('title', 'About — Environmental & Sustainability Specialists')
@section('meta_description', 'SMS Environmental Alliance is a specialized environmental, chemical and sustainability services company supporting responsible industry with assessment, testing, improvement and training.')

@include('public.partials.breadcrumbs', ['label' => 'About', 'url' => route('public.about')])

@section('content')
@php $heroImg = is_file(public_path('images/site/about-facility.webp')); @endphp
<section class="hero">
    <div class="wrap {{ $heroImg ? 'hero-grid' : 'hero-inner' }}" @unless ($heroImg) style="padding-top:64px;padding-bottom:44px" @endunless>
        <div class="{{ $heroImg ? 'hero-copy' : '' }}">
            <span class="eyebrow">About Us</span>
            <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Environmental &amp; sustainability specialists for responsible industry</h1>
            <p>SMS Environmental Alliance supports industrial facilities with practical environmental assessment, testing, chemical management, sustainability improvement and training.</p>
            <div class="hero-cta">
                <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request a Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
                <a class="btn2 btn2--outline" href="{{ route('public.services') }}">Explore Services</a>
            </div>
        </div>
        @if ($heroImg)
            <div class="hero-media reveal">
                <img src="{{ asset('images/site/about-facility.webp') }}?v=2" alt="SMS Environmental Alliance specialist conducting facility monitoring" width="1280" height="960" loading="eager">
            </div>
        @endif
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow">What We Do</span>
                <h2>Specialized, technical, credible</h2>
                <p class="lead">We focus on the environmental, chemical and sustainability performance of factories and industrial facilities — combining hands-on assessment and testing with practical improvement support and capacity building.</p>
                <ul class="ticklist" style="margin-top:16px">
                    <li>@include('public.partials.icon', ['name' => 'check']) Environmental and sustainability expertise</li>
                    <li>@include('public.partials.icon', ['name' => 'check']) Practical industrial experience</li>
                    <li>@include('public.partials.icon', ['name' => 'check']) Technical assessment and testing</li>
                    <li>@include('public.partials.icon', ['name' => 'check']) Improvement support your team can act on</li>
                    <li>@include('public.partials.icon', ['name' => 'check']) Training and capacity building</li>
                </ul>
            </div>
            <div class="split-media">
                <div class="panel">
                    <h3>Our Focus</h3>
                    <ul class="chiplist">
                        <li>@include('public.partials.icon', ['name' => 'leaf']) Environmental Services</li>
                        <li>@include('public.partials.icon', ['name' => 'flask']) Chemical Management</li>
                        <li>@include('public.partials.icon', ['name' => 'globe']) Sustainability Solutions</li>
                        <li>@include('public.partials.icon', ['name' => 'academic']) Environmental &amp; Sustainability Training</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section section--soft">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Our Approach</span>
            <h2>Practical expertise. Measurable improvement.</h2>
            <p>SMS Environmental Alliance combines technical assessment with practical implementation support. Our work is designed not only to identify environmental and sustainability gaps, but to help organizations understand priorities, strengthen controls and improve measurable performance.</p>
        </div>
        <div class="grid grid-3">
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'shield'])</span>
                <h3>Technical Integrity</h3>
                <p>Assessment and testing carried out with method and care, so the findings can be relied on.</p>
            </div>
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'clipboard'])</span>
                <h3>Practical Solutions</h3>
                <p>Recommendations your team can act on — prioritised, realistic and grounded in your facility.</p>
            </div>
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'recycle'])</span>
                <h3>Continuous Improvement</h3>
                <p>Support that helps strengthen controls and improve environmental performance over time.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Industries We Support</span>
            <h2>Across industrial sectors</h2>
        </div>
        <div class="tagcloud">
            @foreach ($industries as $ind)<span>{{ $ind }}</span>@endforeach
        </div>
    </div>
</section>

<section class="section section--brand">
    <div class="wrap" style="text-align:center">
        <h2>Work with a specialist</h2>
        <p style="color:#cfe6da;max-width:620px;margin:.5rem auto 1.4rem">Focused on environmental, chemical and sustainability performance — nothing else to distract from it.</p>
        <a class="btn2 btn2--ghost" href="{{ route('public.contact') }}#proposal">Request a Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
    </div>
</section>
@endsection
