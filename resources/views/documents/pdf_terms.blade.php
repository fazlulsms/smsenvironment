@if ($document->payment_terms)
<div class="section">
    <div class="label">Payment Terms</div>
    {!! nl2br(e($document->payment_terms)) !!}
</div>
@endif

@if (!empty($document->validity_text))
<div class="section">
    <div class="label">Validity</div>
    {!! nl2br(e($document->validity_text)) !!}
</div>
@endif

@if (!empty($document->notes))
<div class="section">
    <div class="label">Notes</div>
    {!! nl2br(e($document->notes)) !!}
</div>
@endif

<div class="signature">
    <div class="label">Prepared By</div>
    @if (!empty($settings['prepared_by_name']))<strong>{{ $settings['prepared_by_name'] }}</strong><br>@endif
    @if (!empty($settings['prepared_by_designation'])){{ $settings['prepared_by_designation'] }}@endif
</div>

<div class="footer">
    {{ $settings['footer_text'] ?? '' }}
    @if (!empty($settings['pdf_note'])) | {{ $settings['pdf_note'] }} @endif
</div>
