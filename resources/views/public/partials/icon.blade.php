@php
    $paths = [
        'leaf' => '<path d="M11 20A7 7 0 0 1 4 13c0-5 4-9 16-9 0 12-4 16-9 16Z"/><path d="M4 21c4-6 8-8 13-9"/>',
        'flask' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3"/><path d="M7 15h10"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'academic' => '<path d="M12 4 2 9l10 5 10-5-10-5Z"/><path d="M6 11v5c0 1 3 3 6 3s6-2 6-3v-5"/>',
        'gauge' => '<path d="M12 13 16 9"/><circle cx="12" cy="14" r="8"/><path d="M12 6V4M5 14H3M21 14h-2"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'clipboard' => '<rect x="8" y="4" width="8" height="4" rx="1"/><path d="M9 4H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"/><path d="M8 12h8M8 16h6"/>',
        'shield' => '<path d="M12 3 5 6v5c0 4 3 8 7 10 4-2 7-6 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
        'water' => '<path d="M12 3s6 6 6 11a6 6 0 0 1-12 0c0-5 6-11 6-11Z"/>',
        'recycle' => '<path d="M7 7 5 10l3 1M17 7l2 3-3 1M9 19l-2-3-3 1M15 19l4-3"/><path d="M12 3 9 7h6l-3-4ZM4 13l-1 5 4-2M20 13l1 5-4-2"/>',
        'check' => '<path d="m5 12 4 4 10-10"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'phone' => '<path d="M4 4h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 2 6a2 2 0 0 1 2-2Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pin' => '<path d="M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    ];
    $size = $size ?? 24;
@endphp
<svg class="ico" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['leaf'] !!}</svg>
