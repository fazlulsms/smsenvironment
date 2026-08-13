<?php

namespace App\Support;

use App\Models\ProformaInvoice;

/**
 * Dual-currency context for an invoice. The transaction currency is what the line
 * amounts are expressed in (e.g. USD); when it differs from the platform base
 * currency (BDT) and a per-invoice conversion rate is present, the document shows
 * both the transaction amount and its base-currency equivalent plus the rate. The
 * conversion rate is always taken from the saved invoice — never hard-coded.
 */
class InvoiceMoney
{
    /** Platform / home currency shared by every entity. */
    public const BASE = 'BDT';

    public static function context(ProformaInvoice $invoice, array $settings = []): array
    {
        $tx = strtoupper((string) ($invoice->currency ?: ($settings['default_currency'] ?? self::BASE))) ?: self::BASE;
        $rate = (float) ($invoice->conversion_rate ?? 0);
        $dual = $tx !== self::BASE && $rate > 0;

        $subtotal = (float) $invoice->subtotal;
        $total = (float) $invoice->total;
        $vat = (float) ($invoice->vat_amount ?? 0);

        return [
            'currency' => $tx,
            'base' => self::BASE,
            'dual' => $dual,
            'rate' => $dual ? $rate : null,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'base_subtotal' => $dual ? round($subtotal * $rate, 2) : null,
            'base_vat' => $dual ? round($vat * $rate, 2) : null,
            'base_total' => $dual ? round($total * $rate, 2) : null,
            // Amount in words ALWAYS matches the displayed primary total + currency
            // (never the converted value, so numbers and words can't disagree). The
            // BDT equivalent, when converting, gets its own explicitly-labelled words.
            'words_amount' => $total,
            'words_currency' => $tx,
            'base_words_amount' => $dual ? round($total * $rate, 2) : null,
        ];
    }
}
