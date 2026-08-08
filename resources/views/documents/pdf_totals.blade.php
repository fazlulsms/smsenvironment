<table class="totals">
    <tr><td>Net Amount</td><td class="text-right">{{ number_format($document->subtotal, 2) }}</td></tr>
    <tr><td>Adjustment</td><td class="text-right">{{ number_format($document->adjustment, 2) }}</td></tr>
    <tr class="grand"><td>Total Payable Amount</td><td class="text-right">{{ number_format($document->total, 2) }}</td></tr>
</table>
<div class="section"><strong>Amount in Words:</strong> {{ $amountInWords }}</div>
