{{-- Restrained page-header photo band. Renders only when the asset is present at
     public/{{ $file }}, so pages stay clean until licensed photography is supplied. --}}
@if (is_file(public_path($file)))
    <section class="section" style="padding-top:0">
        <div class="wrap">
            <div class="page-hero-media reveal">
                <img src="{{ asset($file) }}?v={{ @filemtime(public_path($file)) }}" alt="{{ $alt }}" width="1280" height="720" loading="lazy">
            </div>
        </div>
    </section>
@endif
