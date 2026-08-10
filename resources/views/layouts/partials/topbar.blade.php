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
</header>
