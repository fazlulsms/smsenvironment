<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SMSEA Office' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --smsea-green: #1f6f4a; --smsea-ink: #1f2933; --smsea-line: #d8e2dc; }
        body { background: #f6f8f7; color: var(--smsea-ink); font-size: 14px; }
        .navbar { background: #ffffff; border-bottom: 1px solid var(--smsea-line); }
        .brand-mark { width: 34px; height: 34px; border-radius: 6px; background: var(--smsea-green); color: #fff; display: inline-grid; place-items: center; font-weight: 700; }
        .brand-logo { width: 34px; height: 34px; border-radius: 6px; object-fit: contain; }
        .page-shell { max-width: 1180px; margin: 0 auto; padding: 24px 16px 48px; }
        .panel { background: #fff; border: 1px solid var(--smsea-line); border-radius: 8px; }
        .btn-primary { background: var(--smsea-green); border-color: var(--smsea-green); }
        .btn-primary:hover { background: #18583b; border-color: #18583b; }
        .table > :not(caption) > * > * { padding: .65rem .75rem; }
        textarea.form-control { min-height: 96px; }
        .muted-label { color: #667085; font-size: 12px; text-transform: uppercase; letter-spacing: 0; font-weight: 700; }
        .collapse:not(.show) { display: none; }
    </style>
</head>
<body>
@auth
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                @php($navLogo = \App\Models\Setting::current()->logo_path)
                @if ($navLogo)
                    <img class="brand-logo" src="{{ asset('storage/'.$navLogo) }}" alt="">
                @else
                    <span class="brand-mark">SE</span>
                @endif
                <span class="fw-semibold">SMSEA Office</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ route('clients.index') }}">Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('services.index') }}">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('quotations.index') }}">Quotations</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('proforma-invoices.index') }}">Proforma Invoices</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('bank-accounts.index') }}">Banks</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('settings.edit') }}">Settings</a></li>
                </ul>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">Logout</button>
                </form>
            </div>
        </div>
    </nav>
@endauth

<main class="page-shell">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please check the form.</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
