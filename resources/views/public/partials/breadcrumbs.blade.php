@php
    // Built in a @php block so Blade's @context directive does not corrupt the
    // literal "@context"/"@type" JSON-LD keys.
    $breadcrumbJsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('public.home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => $url],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
@push('jsonld')
<script type="application/ld+json">{!! $breadcrumbJsonLd !!}</script>
@endpush
