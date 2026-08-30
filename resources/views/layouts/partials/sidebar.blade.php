@php
    $counts = $sidebarCounts ?? [];
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name ?? 'U')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $current = app(\App\Support\CurrentEntity::class);
    $currentEntity = $current->get();
    $entities = $current->options();
    $navLogo = $currentEntity?->logo_path ?: \App\Models\Setting::current()->logo_path;
    $entityInitials = fn ($e) => strtoupper(mb_substr($e->short_name ?: $e->name, 0, 2));
@endphp
<aside class="app-sidebar">
    <a class="sidebar-brand" href="{{ route('dashboard') }}">
        <span class="brand-badge">
            @if ($navLogo)
                <img src="{{ asset('storage/'.$navLogo) }}" alt="Logo">
            @else {{ $currentEntity ? $entityInitials($currentEntity) : 'SE' }} @endif
        </span>
        <span class="brand-text"><b>SMSEA Office</b><span>Multi-entity workspace</span></span>
    </a>

    @if ($currentEntity)
        <div class="entity-switch dropdown">
            <button class="entity-switch-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Switch business entity">
                <span class="es-avatar">
                    @if ($currentEntity->logo_path)<img src="{{ asset('storage/'.$currentEntity->logo_path) }}" alt="">@else {{ $entityInitials($currentEntity) }} @endif
                </span>
                <span class="es-meta"><span class="es-label">Business Entity</span><b>{{ $currentEntity->name }}</b></span>
            </button>
            <ul class="dropdown-menu shadow entity-switch-menu">
                <li><h6 class="dropdown-header">Switch entity</h6></li>
                @foreach ($entities as $entity)
                    <li>
                        <form method="post" action="{{ route('entities.switch') }}">
                            @csrf
                            <input type="hidden" name="entity_id" value="{{ $entity->id }}">
                            <button class="dropdown-item d-flex align-items-center gap-2 {{ $entity->id === $currentEntity->id ? 'active' : '' }}" type="submit">
                                <span class="es-color-dot" style="background: {{ $entity->theme()['primary'] }}"></span>
                                <span class="es-dot">{{ $entityInitials($entity) }}</span>
                                <span class="flex-grow-1">{{ $entity->name }}</span>
                                @if ($entity->id === $currentEntity->id)<x-icon name="check" :size="15" />@endif
                            </button>
                        </form>
                    </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('entities.index') }}"><x-icon name="settings" :size="15" class="me-2" />Manage entities</a></li>
                <li><a class="dropdown-item" href="{{ route('entities.overview') }}"><x-icon name="dashboard" :size="15" class="me-2" />All entities overview</a></li>
            </ul>
        </div>
    @endif

    <div class="sidebar-scroll">
        <div class="nav-group">
            <a class="nav-item-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <x-icon name="dashboard" /><span class="label">Dashboard</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Quick Actions</div>
            <a class="nav-item-link {{ request()->routeIs('quick-env.*') ? 'active' : '' }}" href="{{ route('quick-env.index') }}">
                <x-icon name="invoice" /><span class="label">Quick Environmental</span>
            </a>
            <a class="nav-item-link {{ request()->routeIs('ai-draft.*') ? 'active' : '' }}" href="{{ route('ai-draft.index') }}">
                <x-icon name="sparkles" /><span class="label">AI Draft</span>
            </a>
            <a class="nav-item-link" href="{{ route('quotations.create') }}">
                <x-icon name="plus" /><span class="label">New Quotation</span>
            </a>
            <a class="nav-item-link" href="{{ route('proforma-invoices.create') }}">
                <x-icon name="plus" /><span class="label">New Proforma Invoice</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Documents</div>
            <a class="nav-item-link {{ request()->routeIs('inquiries.*') ? 'active' : '' }}" href="{{ route('inquiries.index') }}">
                <x-icon name="email" /><span class="label">Inquiries</span>
                @if (($counts['inquiries_new'] ?? 0) > 0)<span class="nav-badge">{{ $counts['inquiries_new'] }}</span>@endif
            </a>
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
            <a class="nav-item-link {{ request()->routeIs('email-accounts.*') ? 'active' : '' }}" href="{{ route('email-accounts.index') }}">
                <x-icon name="send" /><span class="label">Email Accounts</span>
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
