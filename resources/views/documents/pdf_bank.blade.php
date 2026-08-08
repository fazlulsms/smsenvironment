@if ($bank)
<div class="section bank-block avoid-break">
    <h3>Bank Details</h3>
    <table class="bank-table">
        <tr><td>Beneficiary</td><td>{{ $bank['beneficiary_name'] ?? '' }}</td></tr>
        <tr><td>Bank</td><td>{{ $bank['bank_name'] ?? '' }} @if (!empty($bank['branch'])), {{ $bank['branch'] }} @endif</td></tr>
        <tr><td>Account No.</td><td>{{ $bank['account_number'] ?? '' }}</td></tr>
        @if (!empty($bank['routing_number']))<tr><td>Routing No.</td><td>{{ $bank['routing_number'] }}</td></tr>@endif
        @if (!empty($bank['swift_code']))<tr><td>SWIFT</td><td>{{ $bank['swift_code'] }}</td></tr>@endif
    </table>
</div>
@endif
