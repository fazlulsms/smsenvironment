@if ($bank)
<div class="section">
    <div class="label">Bank Details</div>
    <strong>Beneficiary:</strong> {{ $bank['beneficiary_name'] ?? '' }}<br>
    <strong>Bank:</strong> {{ $bank['bank_name'] ?? '' }} @if (!empty($bank['branch'])), {{ $bank['branch'] }} @endif<br>
    <strong>Account No:</strong> {{ $bank['account_number'] ?? '' }}
    @if (!empty($bank['routing_number']))<br><strong>Routing No:</strong> {{ $bank['routing_number'] }}@endif
    @if (!empty($bank['swift_code']))<br><strong>SWIFT:</strong> {{ $bank['swift_code'] }}@endif
</div>
@endif
