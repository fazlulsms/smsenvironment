<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' · SMSEA Office' : 'SMSEA Office' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/smsea-app.css') }}?v=1" rel="stylesheet">
</head>
<body>
<script>
    // Restore sidebar collapsed state before paint (desktop) to avoid flicker.
    try {
        if (window.matchMedia('(min-width: 992px)').matches &&
            localStorage.getItem('smsea.sidebar') === 'collapsed') {
            document.body.classList.add('sidebar-collapsed');
        }
    } catch (e) {}
</script>

@auth
<div class="app">
    @include('layouts.partials.sidebar')
    <div class="sidebar-backdrop"></div>

    <div class="app-main">
        @include('layouts.partials.topbar')

        <main class="app-content">
            @yield('content')
        </main>
    </div>
</div>

{{-- Feedback toasts --}}
<div class="toast-wrap">
    @if (session('status'))
        <div class="app-toast is-ok" data-autohide>
            <span class="t-ico"><x-icon name="check" :size="18" /></span>
            <div><b>Done</b><small>{{ session('status') }}</small></div>
            <button class="t-close" type="button" aria-label="Dismiss"><x-icon name="x" :size="15" /></button>
        </div>
    @endif
    @if (session('error'))
        <div class="app-toast is-danger" data-autohide>
            <span class="t-ico"><x-icon name="alert" :size="18" /></span>
            <div><b>Something went wrong</b><small>{{ session('error') }}</small></div>
            <button class="t-close" type="button" aria-label="Dismiss"><x-icon name="x" :size="15" /></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="app-toast is-danger" data-autohide>
            <span class="t-ico"><x-icon name="alert" :size="18" /></span>
            <div>
                <b>Please check the form</b>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button class="t-close" type="button" aria-label="Dismiss"><x-icon name="x" :size="15" /></button>
        </div>
    @endif
</div>
@else
    @yield('content')
@endauth

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/smsea-app.js') }}?v=1"></script>
@stack('scripts')
</body>
</html>
