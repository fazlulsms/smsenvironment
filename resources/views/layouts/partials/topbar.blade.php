@php
    $crumb = $crumb ?? match (true) {
        request()->routeIs('dashboard') => 'Overview',
        request()->routeIs('quotations.*') => 'Documents',
        request()->routeIs('proforma-invoices.*') => 'Documents',
        request()->routeIs('clients.*') => 'Master Data',
        request()->routeIs('services.*') => 'Master Data',
        request()->routeIs('email-deliveries.*') => 'Communication',
        request()->routeIs('bank-accounts.*') => 'Configuration',
        request()->routeIs('settings.*') => 'Configuration',
        default => 'SMSEA Office',
    };
@endphp
<header class="app-topbar">
    <button class="topbar-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">
        <x-icon name="menu" :size="19" />
    </button>
    <div class="topbar-title">
        <span class="crumb">{{ $crumb }}</span>
        <h1>{{ $title ?? 'Dashboard' }}</h1>
    </div>
    <div class="topbar-spacer"></div>

    {{-- Most-used shortcuts, always one click away --}}
    <a class="btn btn-sm me-2 d-inline-flex align-items-center gap-1 {{ request()->routeIs('ai-draft.*') ? 'btn-primary' : 'btn-outline-primary' }}"
        href="{{ route('ai-draft.index') }}" title="Smart Paste — paste a request, get a draft">
        <x-icon name="sparkles" :size="16" /> <span class="d-none d-lg-inline">Smart Paste</span>
    </a>
    <a class="btn btn-sm me-2 d-inline-flex align-items-center gap-1 {{ request()->routeIs('quick-env.*') ? 'btn-primary' : 'btn-outline-primary' }}"
        href="{{ route('quick-env.index') }}" title="Quick Environmental Document — EIA / Parameter Testing">
        <x-icon name="invoice" :size="16" /> <span class="d-none d-lg-inline">Quick Env</span>
    </a>

    <div class="dropdown">
        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <x-icon name="plus" :size="16" /> New
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="{{ route('quotations.create') }}"><x-icon name="quotation" :size="16" class="me-2" />New Quotation</a></li>
            <li><a class="dropdown-item" href="{{ route('proforma-invoices.create') }}"><x-icon name="invoice" :size="16" class="me-2" />New Proforma Invoice</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('clients.create') }}"><x-icon name="clients" :size="16" class="me-2" />Add Client</a></li>
            <li><a class="dropdown-item" href="{{ route('services.create') }}"><x-icon name="services" :size="16" class="me-2" />Add Service</a></li>
        </ul>
    </div>

    {{-- Account / user menu --}}
    @php $me = auth()->user(); @endphp
    <div class="dropdown ms-2">
        <button class="account-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
            <x-avatar :user="$me" :size="32" />
            <span class="account-meta d-none d-md-flex">
                <b>{{ $me->name }}</b>
                <span class="badge-soft {{ $me->roleBadgeClass() }}">{{ $me->roleLabel() }}</span>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li class="dropdown-header d-md-none">{{ $me->name }} · {{ $me->roleLabel() }}</li>
            <li><a class="dropdown-item" href="{{ route('profile.show') }}"><x-icon name="name" :size="16" class="me-2" />My Profile</a></li>
            @can('manage-settings')
                <li><a class="dropdown-item" href="{{ route('settings.edit') }}"><x-icon name="settings" :size="16" class="me-2" />Settings</a></li>
            @endcan
            @can('manage-users')
                <li><a class="dropdown-item" href="{{ route('users.index') }}"><x-icon name="clients" :size="16" class="me-2" />Users</a></li>
            @endcan
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit"><x-icon name="logout" :size="16" class="me-2" />Log out</button>
                </form>
            </li>
        </ul>
    </div>
</header>
