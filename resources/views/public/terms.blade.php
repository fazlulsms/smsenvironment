@extends('public.layouts.site')
@section('title', 'Terms of Use')
@section('meta_description', 'Terms of Use for the SMS Environmental Alliance website.')

@section('content')
<section class="section">
    <div class="wrap legal">
        <span class="eyebrow">Legal</span>
        <h1>Terms of Use</h1>
        <p class="lead">By using this website you agree to the following terms.</p>

        <h2>Purpose of this website</h2>
        <p>This website provides general information about the environmental, chemical, sustainability and training services offered by SMS Environmental Alliance. It is for information and enquiry purposes only.</p>

        <h2>No warranty</h2>
        <p>Content is provided in good faith and may change without notice. Service scopes, methods and outcomes are confirmed in a written proposal and agreement for each engagement. Nothing on this website constitutes a binding offer or a guarantee of a specific regulatory outcome.</p>

        <h2>Enquiries</h2>
        <p>Information you submit through the enquiry form should be accurate. We will use it to respond as described in our <a href="{{ route('public.privacy') }}">Privacy Policy</a>.</p>

        <h2>Intellectual property</h2>
        <p>The SMS Environmental Alliance name, content and branding on this website may not be copied or reused without permission.</p>

        <h2>Contact</h2>
        <p>{{ $contact['name'] }}<br>{{ $contact['address'] }}<br>
            <a href="tel:+8801873035178">{{ $contact['phone'] }}</a> · <a href="mailto:info@smsenvironment.com">{{ $contact['email'] }}</a></p>
    </div>
</section>
@endsection
