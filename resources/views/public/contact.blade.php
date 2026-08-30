@extends('public.layouts.site')
@section('title', 'Contact & Request a Proposal')
@section('meta_description', 'Contact SMS Environmental Alliance for environmental assessment, testing, chemical management, sustainability services and training. Request a proposal for your facility.')

@section('content')
<section class="hero">
    <div class="wrap hero-inner" style="padding:64px 0 40px">
        <span class="eyebrow">Contact</span>
        <h1 style="font-size:clamp(1.9rem,4.4vw,2.9rem)">Request a Proposal</h1>
        <p>Tell us about your facility and what you need assessed, tested, managed or trained — we’ll respond with a focused proposal.</p>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="inquiry">
            <div>
                <span class="eyebrow">Get in touch</span>
                <h2>SMS Environmental Alliance</h2>
                <p class="lead">We support factories, manufacturers and industrial facilities across Bangladesh.</p>
                <ul class="contact-list">
                    <li>@include('public.partials.icon', ['name' => 'pin']) <span>{{ $contact['address'] }}</span></li>
                    <li>@include('public.partials.icon', ['name' => 'phone']) <a href="tel:+8801873035178">{{ $contact['phone'] }}</a></li>
                    <li>@include('public.partials.icon', ['name' => 'mail']) <a href="mailto:info@smsenvironment.com">{{ $contact['email'] }}</a></li>
                    <li>@include('public.partials.icon', ['name' => 'globe']) <a href="https://www.smsenvironment.com">{{ $contact['website'] }}</a></li>
                </ul>
            </div>
            @include('public.partials.inquiry')
        </div>
    </div>
</section>
@endsection
