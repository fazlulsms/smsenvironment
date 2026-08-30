<div class="form-card" id="proposal">
    @if (session('inquiry_status'))
        <div class="alert-ok">{{ session('inquiry_status') }}</div>
    @endif
    <form method="post" action="{{ route('public.inquiry') }}">
        @csrf
        <input type="hidden" name="website_url" value="" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-row">
            <div class="field">
                <label for="q-name">Name <span aria-hidden="true">*</span></label>
                <input id="q-name" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="q-company">Company</label>
                <input id="q-company" name="company" value="{{ old('company') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="q-email">Email <span aria-hidden="true">*</span></label>
                <input id="q-email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="q-phone">Phone</label>
                <input id="q-phone" name="phone" value="{{ old('phone') }}">
            </div>
        </div>
        <div class="field">
            <label for="q-service">Service Interested In</label>
            <select id="q-service" name="service">
                <option value="">— Select a service —</option>
                @foreach ($serviceOptions as $opt)
                    <option value="{{ $opt }}" @selected(old('service') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="q-message">Message</label>
            <textarea id="q-message" name="message" rows="4" placeholder="Tell us about your facility and requirement.">{{ old('message') }}</textarea>
            @error('message')<div class="err">{{ $message }}</div>@enderror
        </div>
        <button class="btn2 btn2--primary" type="submit">
            @include('public.partials.icon', ['name' => 'arrow', 'size' => 18]) Send Request
        </button>
    </form>
</div>
