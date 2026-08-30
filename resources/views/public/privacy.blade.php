@extends('public.layouts.site')
@section('title', 'Privacy Policy')
@section('meta_description', 'Privacy Policy for the SMS Environmental Alliance website.')

@section('content')
<section class="section">
    <div class="wrap legal">
        <span class="eyebrow">Legal</span>
        <h1>Privacy Policy</h1>
        <p class="lead">This policy explains how SMS Environmental Alliance handles information submitted through this website.</p>

        <h2>Information we collect</h2>
        <p>When you submit the “Request a Proposal” form, we collect the details you provide — your name, company, email, phone, the service you are interested in, and your message. We do not collect this information in any other way on this website.</p>

        <h2>How we use it</h2>
        <p>We use your information solely to respond to your enquiry and to prepare and discuss a proposal for the environmental, chemical, sustainability or training services you request. We do not sell your information.</p>

        <h2>Sharing</h2>
        <p>Your information is handled by SMS Environmental Alliance staff involved in responding to your enquiry. We do not share it with third parties for marketing.</p>

        <h2>Retention</h2>
        <p>We retain enquiry details for as long as needed to respond to and follow up on your request, and as required for our legitimate business records.</p>

        <h2>Your requests</h2>
        <p>To ask about, correct or remove the information you submitted, contact us using the details below.</p>

        <h2>Contact</h2>
        <p>{{ $contact['name'] }}<br>{{ $contact['address'] }}<br>
            <a href="tel:+8801873035178">{{ $contact['phone'] }}</a> · <a href="mailto:info@smsenvironment.com">{{ $contact['email'] }}</a></p>
    </div>
</section>
@endsection
