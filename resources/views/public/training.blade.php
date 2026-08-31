@extends('public.layouts.site')
@section('title', 'Environmental & Sustainability Training')
@section('meta_description', 'Environmental and sustainability training in Bangladesh — environmental compliance, chemical management, energy efficiency, carbon/GHG, waste, ETP/wastewater, cleaner production and resource efficiency.')

@include('public.partials.breadcrumbs', ['label' => 'Training', 'url' => route('public.training')])

@section('content')
<section class="hero">
    <div class="wrap hero-inner" style="padding-top:64px;padding-bottom:44px">
        <span class="eyebrow">Training &amp; Capacity Building</span>
        <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Environmental &amp; Sustainability Training</h1>
        <p>Build in-house capability across environmental, chemical and sustainability topics — delivered in-house, as public programs, or customized to your facility.</p>
    </div>
</section>

@include('public.partials.page_header_image', ['file' => 'images/site/training-workshop.webp', 'alt' => 'On-site environmental assessment and team briefing at an industrial facility'])

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Training Categories</span>
            <h2>Focused, practical, industry-relevant</h2>
        </div>
        <div class="service-cols">
            @foreach ($families['training']['services'] as $t)
                <div class="item">@include('public.partials.icon', ['name' => 'check']) <span>{{ $t }}</span></div>
            @endforeach
        </div>
    </div>
</section>

<section class="section section--soft">
    <div class="wrap">
        <div class="section-head center">
            <span class="eyebrow">Delivery Formats</span>
            <h2>Training that fits your team</h2>
        </div>
        <div class="grid grid-3">
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'academic'])</span>
                <h3>In-house Training</h3>
                <p>Delivered at your facility, focused on your processes and requirements.</p>
            </div>
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'globe'])</span>
                <h3>Public Training</h3>
                <p>Scheduled programs on core environmental and sustainability topics.</p>
            </div>
            <div class="pillar">
                <span class="ico-wrap">@include('public.partials.icon', ['name' => 'clipboard'])</span>
                <h3>Customized Capacity Building</h3>
                <p>Tailored curricula aligned to your improvement objectives.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section--brand">
    <div class="wrap" style="text-align:center">
        <h2>Plan a training program</h2>
        <p style="color:#cfe6da;max-width:620px;margin:.5rem auto 1.4rem">Tell us your topic and team size — we’ll propose an in-house, public or customized program.</p>
        <a class="btn2 btn2--ghost" href="{{ route('public.contact') }}#proposal">Request Training Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
    </div>
</section>
@endsection
