<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SMS Environmental Alliance') — Environmental, Chemical & Sustainability Solutions</title>
    <meta name="description" content="@yield('meta_description', 'SMS Environmental Alliance provides environmental assessment, testing, chemical management, sustainability solutions and professional training for responsible industry in Bangladesh.')">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'SMS Environmental Alliance')">
    <meta property="og:description" content="@yield('meta_description', 'Environmental, Chemical & Sustainability Solutions for Responsible Industry.')">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SMS Environmental Alliance">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/brand/smsea-logo.png') }}">
    <meta name="twitter:card" content="summary">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/smsea-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/smsea-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/smsea-site.css') }}?v=8" rel="stylesheet">
    @php
        // Built in a @php block so Blade's @context directive never touches the
        // literal "@context"/"@type" JSON-LD keys (that would corrupt the markup).
        $c = \App\Support\PublicSite::contact();
        $orgJsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => 'SMS Environmental Alliance',
            'description' => 'Environmental assessment and testing, chemical management, sustainability solutions and professional training for responsible industry in Bangladesh.',
            'url' => url('/'),
            'logo' => asset('images/brand/smsea-logo.png'),
            'image' => asset('images/brand/smsea-logo.png'),
            'telephone' => $c['phone'],
            'email' => $c['email'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town',
                'addressLocality' => 'Dhaka',
                'postalCode' => '1230',
                'addressCountry' => 'BD',
            ],
            'areaServed' => 'Bangladesh',
            'knowsAbout' => ['Environmental Impact Assessment', 'Environmental Parameter Testing', 'Environmental Compliance Audit', 'Energy Audit', 'Chemical Management', 'Carbon Footprint', 'GHG Inventory', 'Wastewater and ETP Assessment', 'Cleaner Production', 'Environmental Training'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    <script type="application/ld+json">{!! $orgJsonLd !!}</script>
    @stack('jsonld')
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <nav class="nav" id="siteNav">
            <a class="brand" href="{{ route('public.home') }}" aria-label="SMS Environmental Alliance — Home">
                <img class="brand-logo" src="{{ asset('images/brand/smsea-logo.png') }}" alt="SMS Environmental Alliance" width="300" height="300">
            </a>
            <div class="nav-links">
                <a href="{{ route('public.home') }}" class="{{ request()->routeIs('public.home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('public.services') }}" class="{{ request()->routeIs('public.services') ? 'active' : '' }}">Services</a>
                <a href="{{ route('public.training') }}" class="{{ request()->routeIs('public.training') ? 'active' : '' }}">Training</a>
                <a href="{{ route('public.about') }}" class="{{ request()->routeIs('public.about') ? 'active' : '' }}">About</a>
                <a href="{{ route('verify.index') }}" class="{{ request()->routeIs('verify.*') ? 'active' : '' }}">Verify</a>
                <a href="{{ route('public.contact') }}" class="{{ request()->routeIs('public.contact') ? 'active' : '' }}">Contact</a>
            </div>
            <div class="nav-cta">
                <a class="nav-office" href="{{ route('dashboard') }}">Office</a>
                <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request a Proposal</a>
                <button class="nav-toggle" type="button" aria-label="Menu" onclick="document.getElementById('siteNav').classList.toggle('open')">
                    @include('public.partials.icon', ['name' => 'arrow', 'size' => 20])
                </button>
            </div>
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <img class="footer-logo" src="{{ asset('images/brand/smsea-logo.png') }}" alt="SMS Environmental Alliance" width="300" height="300">
                <p>Environmental assessment &amp; testing, chemical management, sustainability solutions and professional training for responsible industry.</p>
            </div>
            <div>
                <h4>Explore</h4>
                <a href="{{ route('public.services') }}">Services</a>
                <a href="{{ route('public.training') }}">Training</a>
                <a href="{{ route('public.about') }}">About</a>
                <a href="{{ route('verify.index') }}">Verify</a>
                <a href="{{ route('public.contact') }}">Contact</a>
            </div>
            <div>
                <h4>Services</h4>
                <a href="{{ route('public.services') }}#environmental">Environmental</a>
                <a href="{{ route('public.services') }}#chemical">Chemical Management</a>
                <a href="{{ route('public.services') }}#sustainability">Sustainability</a>
                <a href="{{ route('public.training') }}">Training</a>
            </div>
            <div>
                <h4>Contact</h4>
                <p style="margin:0 0 .5rem">{{ \App\Support\PublicSite::contact()['address'] }}</p>
                <a href="tel:+8801873035178">+8801873035178</a>
                <a href="mailto:info@smsenvironment.com">info@smsenvironment.com</a>
            </div>
        </div>
        <div class="footer-verify">
            Received a document from us? <a href="{{ route('verify.index') }}">Verify a document →</a>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} SMS Environmental Alliance. All rights reserved.</span>
            <span>
                <a href="{{ route('public.privacy') }}">Privacy Policy</a> ·
                <a href="{{ route('public.terms') }}">Terms of Use</a> ·
                <a href="{{ route('dashboard') }}">Office Login</a>
            </span>
        </div>
    </div>
</footer>

{{-- Lightweight, dependency-free reveal. Content is visible without JS; this only
     adds a subtle entrance when supported, with a guaranteed-visible fallback. --}}
<script>
    document.documentElement.classList.add('js');
    (function () {
        var els = [].slice.call(document.querySelectorAll('.reveal'));
        if (!els.length) return;
        var reveal = function (el) { el.classList.add('is-in'); };
        if (!('IntersectionObserver' in window)) { els.forEach(reveal); return; }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { reveal(e.target); io.unobserve(e.target); } });
        }, { rootMargin: '0px 0px -8% 0px' });
        els.forEach(function (el) { io.observe(el); });
        // Safety net: never leave content hidden.
        setTimeout(function () { els.forEach(reveal); }, 1800);
    })();
</script>
</body>
</html>
