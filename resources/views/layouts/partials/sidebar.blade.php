@php
    $navLogo = \App\Models\Setting::current()->logo_path;
    $counts = $sidebarCounts ?? [];
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name ?? 'U')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp
<aside class="app-sidebar">
    <a class="sidebar-brand" href="{{ route('dashboard') }}">
        <span class="brand-badge">
            @if ($navLogo)
                <img src="{{ asset('storage/'.$navLogo) }}" alt="SMSEA">
            @else SE @endif
        </span>
        <span class="brand-text"><b>SMSEA Office</b><span>Environmental Alliance</span></span>
    </a>

    <div class="sidebar-scroll">
        <div class="nav-group">
            <a class="nav-item-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <x-icon name="dashboard" /><span class="label">Dashboard</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Documents</div>
            <a class="nav-item-link {{ request()->routeIs('quotations.*') ? 'active' : '' }}" href="{{ route('quotations.index') }}">
                <x-icon name="quotation" /><span class="label">Quotations</span>
                @if (($counts['quotations'] ?? 0) > 0)<span class="nav-badge">{{ $counts['quotations'] }}</span>@endif
            </a>
            <a class="nav-item-link {{ request()->routeIs('proforma-invoices.*') ? 'active' : '' }}" href="{{ route('proforma-invoices.index') }}">
                <x-icon name="invoice" /><span class="label">Proforma Invoices</span>
                @if (($counts['invoices'] ?? 0) > 0)<span class="nav-badge">{{ $counts['invoices'] }}</span>@endif
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Master Data</div>
            <a class="nav-item-link {{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ route('clients.index') }}">
                <x-icon name="clients" /><span class="label">Clients</span>
                @if (($counts['clients'] ?? 0) > 0)<span class="nav-badge">{{ $counts['clients'] }}</span>@endif
            </a>
            <a class="nav-item-link {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}">
                <x-icon name="services" /><span class="label">Services</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Communication</div>
            <a class="nav-item-link {{ request()->routeIs('email-deliveries.*') ? 'active' : '' }}" href="{{ route('email-deliveries.index') }}">
                <x-icon name="email" /><span class="label">Email History</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Configuration</div>
            <a class="nav-item-link {{ request()->routeIs('bank-accounts.*') ? 'active' : '' }}" href="{{ route('bank-accounts.index') }}">
                <x-icon name="bank" /><span class="label">Bank Accounts</span>
            </a>
            <a class="nav-item-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}">
                <x-icon name="settings" /><span class="label">Settings</span>
            </a>
        </div>
    </div>

    <div class="sidebar-foot">
        <div class="side-user">
            <span class="avatar">{{ strtoupper($initials ?: 'U') }}</span>
            <span class="u-meta"><b>{{ $user->name }}</b><span>{{ $user->email }}</span></span>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="side-logout" type="submit" title="Log out" aria-label="Log out"><x-icon name="logout" :size="17" /></button>
            </form>
        </div>
    </div>
</aside>
