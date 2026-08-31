@extends('public.layouts.site')
@section('title', 'SMS Environmental Alliance')
@section('meta_description', 'Environmental consultancy in Bangladesh: EIA, environmental parameter testing, energy audit, chemical management, carbon & GHG inventory, wastewater/ETP assessment and environmental training for industry.')

@section('content')
{{-- HERO — a hero photo is shown when supplied at public/images/site/hero-industrial.webp;
     otherwise the approved clean single-column hero is used unchanged. --}}
@php $heroImg = is_file(public_path('images/site/hero-industrial.webp')); @endphp
<section class="hero">
    <div class="wrap {{ $heroImg ? 'hero-grid' : 'hero-inner' }}">
        <div class="{{ $heroImg ? 'hero-copy' : '' }}">
            <span class="eyebrow">SMS Environmental Alliance</span>
            <h1>Environmental, Chemical &amp; Sustainability Solutions for Responsible Industry</h1>
            <p>{{ \App\Support\PublicSite::INTRO }}</p>
            <div class="hero-cta">
                <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request a Proposal @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
                <a class="btn2 btn2--outline" href="{{ route('public.services') }}">Explore Services</a>
            </div>
            <div class="hero-strip">
                <span>@include('public.partials.icon', ['name' => 'check']) Environmental Assessment &amp; Testing</span>
                <span>@include('public.partials.icon', ['name' => 'check']) Chemical Management</span>
                <span>@include('public.partials.icon', ['name' => 'check']) Sustainability Solutions</span>
                <span>@include('public.partials.icon', ['name' => 'check']) Training &amp; Capacity Building</span>
            </div>
        </div>
        @if ($heroImg)
            <div class="hero-media reveal">
                <img src="{{ asset('images/site/hero-industrial.webp') }}" alt="SMS Environmental Alliance specialist carrying out noise and emission monitoring on plant equipment" width="1200" height="960" fetchpriority="high">
            </div>
        @endif
    </div>
</section>

{{-- CORE EXPERTISE --}}
<section class="section">
    <div class="wrap">
        <div class="section-head center">
            <span class="eyebrow">Core Expertise</span>
            <h2>Specialized in environmental, chemical &amp; sustainability performance</h2>
            <p>Practical, technical support for factories, manufacturers and industrial facilities.</p>
        </div>
        <div class="grid grid-4">
            @foreach ($families as $family)
                <a class="pillar reveal" href="{{ route('public.services') }}#{{ $family['key'] }}">
                    <span class="ico-wrap">@include('public.partials.icon', ['name' => $family['icon']])</span>
                    <h3>{{ $family['title'] }}</h3>
                    <p>{{ $family['tagline'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- FEATURED SERVICES --}}
<section class="section section--soft">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Featured Services</span>
            <h2>Where industry works with us most</h2>
            <p>The environmental, chemical and sustainability services our clients request most often.</p>
        </div>
        <div class="grid grid-3">
            @foreach ($featured as $f)
                <div class="feature reveal {{ $loop->iteration <= 6 ? 'feature--primary' : '' }}">
                    <span class="ico-wrap">@include('public.partials.icon', ['name' => $f['icon']])</span>
                    <div>
                        <h3>{{ $f['title'] }}</h3>
                        <p>{{ $f['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- FIELD WORK PHOTO BAND (renders only when the photo is present) --}}
@include('public.partials.page_header_image', ['file' => 'images/site/home-fieldwork.webp', 'alt' => 'Ambient air quality monitoring at an industrial facility in Bangladesh'])

{{-- ENVIRONMENTAL PARAMETER TESTING --}}
<section class="section" id="testing">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow">Environmental Testing &amp; Assessment</span>
                <h2>Environmental Parameter Testing</h2>
                <p class="lead">One coordinated package covering the environmental parameters industrial facilities are most frequently asked to assess — measured, reviewed and reported.</p>
                <ul class="chiplist">
                    @foreach ($testingScope as $param)
                        <li>@include('public.partials.icon', ['name' => 'check']) {{ $param }}</li>
                    @endforeach
                </ul>
                <div style="margin-top:22px">
                    <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request Environmental Testing Proposal</a>
                </div>
            </div>
            <div class="split-media">
                <div class="panel">
                    <h3>What you receive</h3>
                    <ul class="ticklist">
                        <li>@include('public.partials.icon', ['name' => 'check']) On-site measurement and sampling</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Assessment against applicable reference levels</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Clear, documented parameter results</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Practical improvement recommendations</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Professional reporting for your records</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- EIA --}}
<section class="section section--soft" id="eia">
    <div class="wrap">
        <div class="split split--reverse">
            <div class="split-media">
                <div class="panel">
                    <h3>EIA scope</h3>
                    <ul class="ticklist">
                        <li>@include('public.partials.icon', ['name' => 'check']) Project / facility environmental assessment</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Baseline review</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Regulatory / document review</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Impact identification</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Mitigation recommendations</li>
                        <li>@include('public.partials.icon', ['name' => 'check']) Reporting &amp; environmental management planning</li>
                    </ul>
                </div>
            </div>
            <div>
                <span class="eyebrow">Environmental Assessment</span>
                <h2>Environmental Impact Assessment (EIA)</h2>
                <p class="lead">Structured environmental assessment of a project or facility — from baseline and document review through impact identification, mitigation and reporting, into a practical environmental management plan.</p>
                <div style="margin-top:20px">
                    <a class="btn2 btn2--primary" href="{{ route('public.contact') }}#proposal">Request EIA Proposal</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CHEMICAL MANAGEMENT --}}
<section class="section" id="chemical">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Chemical Management</span>
            <h2>Practical chemical management for industry</h2>
            <p>From inventory to improvement — a working system your team can actually run.</p>
        </div>
        <ul class="chiplist" style="gap:12px">
            @foreach (['Inventory','Risk Assessment','Storage','Labeling','Handling','Documentation','Training','Restricted Substance Management','Improvement Planning'] as $c)
                <li>@include('public.partials.icon', ['name' => 'flask']) {{ $c }}</li>
            @endforeach
        </ul>
    </div>
</section>

{{-- SUSTAINABILITY --}}
<section class="section section--soft" id="sustainability">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Sustainability Solutions</span>
            <h2>Measurable environmental performance</h2>
            <p>Concrete outcomes across energy, carbon, water, waste, resources and cleaner production.</p>
        </div>
        <div class="grid grid-3">
            @foreach ([
                ['bolt','Energy','Energy audits that identify and quantify real savings.'],
                ['globe','Carbon','Carbon footprint and GHG inventory you can defend.'],
                ['water','Water','Water footprint and wastewater / ETP performance.'],
                ['recycle','Waste & Resources','Waste reduction, resource efficiency and circularity.'],
                ['leaf','Cleaner Production','Lower resource use and cost at the source.'],
                ['clipboard','Performance & Reporting','Environmental data review and sustainability reporting support.'],
            ] as $s)
                <div class="feature">
                    <span class="ico-wrap">@include('public.partials.icon', ['name' => $s[0]])</span>
                    <div><h3>{{ $s[1] }}</h3><p>{{ $s[2] }}</p></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- TRAINING --}}
<section class="section section--brand" id="training">
    <div class="wrap">
        <div class="split">
            <div>
                <span class="eyebrow" style="color:var(--accent)">Training &amp; Capacity Building</span>
                <h2>Build in-house environmental capability</h2>
                <p style="color:#cfe6da;font-size:1.08rem">In-house, public and customized training across environmental, chemical and sustainability topics — so your team can sustain performance between assessments.</p>
                <div style="margin-top:20px">
                    <a class="btn2 btn2--ghost" href="{{ route('public.training') }}">View Training Programs @include('public.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
                </div>
            </div>
            <div class="split-media">
                <div class="panel on-brand">
                    <ul class="chiplist">
                        @foreach ($trainingCategories as $t)
                            <li>@include('public.partials.icon', ['name' => 'check']) {{ $t }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- HOW WE WORK --}}
<section class="section">
    <div class="wrap">
        <div class="section-head center">
            <span class="eyebrow">How We Work</span>
            <h2>A simple, practical engagement</h2>
        </div>
        <div class="steps">
            @foreach ($howWeWork as $step)
                <div class="step">
                    <div class="num">{{ $step['step'] }}</div>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- INDUSTRIES --}}
<section class="section section--soft">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Industries We Support</span>
            <h2>Environmental expertise across industrial sectors</h2>
        </div>
        <div class="tagcloud">
            @foreach ($industries as $ind)<span>{{ $ind }}</span>@endforeach
        </div>
    </div>
</section>

{{-- REQUEST A PROPOSAL --}}
<section class="section" id="request">
    <div class="wrap">
        <div class="inquiry">
            <div>
                <span class="eyebrow">Request a Proposal</span>
                <h2>Let’s scope your environmental requirement</h2>
                <p class="lead">Tell us about your facility and what you need assessed, tested, managed or trained. We’ll respond with a focused proposal.</p>
                <ul class="contact-list">
                    <li>@include('public.partials.icon', ['name' => 'pin']) <span>{{ $contact['address'] }}</span></li>
                    <li>@include('public.partials.icon', ['name' => 'phone']) <a href="tel:+8801873035178">{{ $contact['phone'] }}</a></li>
                    <li>@include('public.partials.icon', ['name' => 'mail']) <a href="mailto:info@smsenvironment.com">{{ $contact['email'] }}</a></li>
                </ul>
            </div>
            @include('public.partials.inquiry')
        </div>
    </div>
</section>
@endsection
