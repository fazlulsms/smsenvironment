<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SMS Environmental Alliance') — Environmental, Chemical & Sustainability Solutions</title>
    <meta name="description" content="@yield('meta_description', 'SMS Environmental Alliance provides environmental assessment, testing, chemical management, sustainability solutions and professional training for responsible industry in Bangladesh.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'SMS Environmental Alliance')">
    <meta property="og:description" content="@yield('meta_description', 'Environmental, Chemical & Sustainability Solutions for Responsible Industry.')">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/brand/smsea-logo.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/brand/smsea-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/smsea-site.css') }}?v=1" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <nav class="nav" id="siteNav">
            <a class="brand" href="{{ route('public.home') }}">
                <img class="brand-logo" src="{{ asset('images/brand/smsea-logo.png') }}" alt="SMS Environmental Alliance" width="300" height="300">
                <span class="brand-name">SMS Environmental Alliance<span>Environmental · Chemical · Sustainability</span></span>
            </a>
            <div class="nav-links">
                <a href="{{ route('public.home') }}" class="{{ request()->routeIs('public.home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('public.services') }}" class="{{ request()->routeIs('public.services') ? 'active' : '' }}">Services</a>
                <a href="{{ route('public.training') }}" class="{{ request()->routeIs('public.training') ? 'active' : '' }}">Training</a>
                <a href="{{ route('public.about') }}" class="{{ request()->routeIs('public.about') ? 'active' : '' }}">About</a>
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
</body>
</html>
