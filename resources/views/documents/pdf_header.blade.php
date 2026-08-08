<div class="header row">
    <div class="col">
        <div class="brand-line">
            @if (!empty($settings['logo_path']) && file_exists(storage_path('app/public/'.$settings['logo_path'])))
                <div class="logo-cell"><img class="logo" src="{{ storage_path('app/public/'.$settings['logo_path']) }}" alt=""></div>
            @endif
            <div class="brand-cell">
                <div class="brand">{{ $settings['organization_name'] ?? 'SMS Environmental Alliance' }}</div>
                <div class="tagline">{{ $settings['tagline'] ?? '' }}</div>
                <div>{{ $settings['office_address'] ?? '' }}</div>
                <div>{{ $settings['phone'] ?? '' }} @if (!empty($settings['email'])) | {{ $settings['email'] }} @endif @if (!empty($settings['website'])) | {{ $settings['website'] }} @endif</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="doc-title">{{ $title }}</div>
        <div class="meta">
            <strong>No:</strong> {{ $number }}<br>
            <strong>Date:</strong> {{ $date->format('d M Y') }}
        </div>
    </div>
</div>
