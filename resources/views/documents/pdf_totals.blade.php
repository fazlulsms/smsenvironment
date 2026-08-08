<table class="totals">
    <tr><td>Net Amount</td><td class="text-right">{{ number_format($document->subtotal, 2) }}</td></tr>
    @if ((float) $document->adjustment !== 0.0)
        <tr><td>Discount / Adjustment</td><td class="text-right">{{ number_format($document->adjustment, 2) }}</td></tr>
    @endif
    @if (($document->vat_treatment ?? null) === 'add' && (float) ($document->vat_amount ?? 0) > 0 && ($document->show_vat_separately ?? true))
        <tr><td>VAT @ {{ rtrim(rtrim(number_format((float) $document->vat_rate, 3), '0'), '.') }}%</td><td class="text-right">{{ number_format($document->vat_amount, 2) }}</td></tr>
    @endif
    <tr class="grand"><td>Total Payable Amount</td><td class="text-right">{{ number_format($document->total, 2) }}</td></tr>
</table>
<div class="section"><strong>Amount in Words:</strong> {{ $amountInWords }}</div>
