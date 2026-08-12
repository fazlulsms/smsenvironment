<?php

namespace App\Support;

use App\Models\ProformaInvoice;

/**
 * Resolves the per-entity document profile for a proforma invoice: which PDF view
 * renders it and whether the verification (QR) block participates. This is the
 * single place that maps an entity to its document identity so the PDF service,
 * the detail preview and tests all agree. Shared business logic (numbering, VAT,
 * snapshots, email) is untouched — only presentation differs per profile.
 */
class DocumentProfile
{
    public static function forInvoice(ProformaInvoice $invoice): array
    {
        return self::forEntityCode($invoice->entity_code);
    }

    public static function forEntityCode(?string $code): array
    {
        return match ($code) {
            'EIDIKOS' => [
                'key' => 'eidikos',
                'pdf_view' => 'proforma_invoices.eidikos_pdf',
                'show_verification' => false,
            ],
            default => [
                'key' => 'default',
                'pdf_view' => 'proforma_invoices.pdf',
                'show_verification' => true,
            ],
        };
    }

    public static function isEidikos(?string $code): bool
    {
        return $code === 'EIDIKOS';
    }
}
