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
        $sequence = $model::query()->whereYear('date', $year)->count() + 1;

        do {
            $number = strtr($format ?: $fallback, [
                '{YYYY}' => $year,
                '{YY}' => now()->format('y'),
                '{####}' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                '{###}' => str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            ]);
            $sequence++;
        } while ($model::query()->where('number', $number)->exists());

        return $number;
    }
}
