@extends('layouts.app', ['title' => 'Sign in'])

@php $navLogo = \App\Models\Setting::current()->logo_path; @endphp

@section('content')
<div class="auth-shell">
    <div class="auth-aside">
        <div class="aa-brand">
            <span class="brand-badge">@if ($navLogo)<img src="{{ asset('storage/'.$navLogo) }}" alt="SMSEA">@else SE @endif</span>
            <div><div class="fw-semibold text-white">SMSEA Office</div><div style="color:#8ba296;font-size:12px">SMS Environmental Alliance</div></div>
        </div>
        <div class="aa-hero">
            <h2>Quotations &amp; invoices,<br>without the manual work.</h2>
            <p>Select a client, pick a service, set the amount — generate a professional document in a few clicks.</p>
            <ul class="aa-points">
                <li><x-icon name="check" :size="16" /> Smart Paste client details</li>
                <li><x-icon name="check" :size="16" /> Professional quotation &amp; proforma PDFs</li>
                <li><x-icon name="check" :size="16" /> QR verification &amp; email delivery</li>
            </ul>
        </div>
    </div>

    <div class="auth-main">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="sub">Sign in to continue to SMSEA Office.</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('login.store') }}" data-loading>
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email address</label>
                    <input class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@smsenvironment.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required placeholder="••••••••">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">Keep me signed in</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</div>
@endsection
