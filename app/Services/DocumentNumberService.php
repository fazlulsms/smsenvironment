<?php

namespace App\Services;

use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Setting;

class DocumentNumberService
{
    public function quotation(): string
    {
        return $this->format(Setting::current()->quotation_number_format, Quotation::class, 'SMSEA/QT/{YYYY}/{####}');
    }

    public function invoice(): string
    {
        return $this->format(Setting::current()->invoice_number_format, ProformaInvoice::class, 'SMSEA/PI/{YYYY}/{####}');
    }

    private function format(?string $format, string $model, string $fallback): string
    {
        $year = now()->format('Y');

        // Include soft-deleted documents when counting and checking for collisions.
        // A deleted quotation/invoice must NEVER free its number for reuse, so the
        // sequence stays monotonic even after deletions. Quotation and
        // ProformaInvoice both use SoftDeletes, so withTrashed() sees every number
        // ever issued.
        $sequence = $model::withTrashed()->whereYear('date', $year)->count() + 1;

        do {
            $number = strtr($format ?: $fallback, [
                '{YYYY}' => $year,
                '{YY}' => now()->format('y'),
                '{####}' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                '{###}' => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            ]);
            $sequence++;
        } while ($model::withTrashed()->where('number', $number)->exists());

        return $number;
    }
}
