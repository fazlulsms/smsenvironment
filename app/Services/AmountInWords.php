<?php

namespace App\Services;

class AmountInWords
{
    private array $ones = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private array $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    public function convert(
        float|int|string $amount,
        string $currency = 'BDT',
        string $majorName = 'Taka',
        string $minorName = 'Paisa'
    ): string
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$wholePart, $decimalPart] = explode('.', $normalized);
        $whole = (int) $wholePart;
        $minor = (int) $decimalPart;

        if (strtoupper($currency) === 'BDT') {
            $words = $this->numberToWords($whole).' '.$majorName;

            if ($minor > 0) {
                $words .= ' and '.$this->numberToWords($minor).' '.$minorName;
            }

            return trim($words.' Only');
        }

        $words = trim($currency.' '.$this->numberToWords($whole));

        if ($minor > 0) {
            $words .= ' and '.$this->numberToWords($minor).'/100';
        }

        return trim($words.' Only');
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        if ($number < 20) {
            return $this->ones[$number];
        }

        if ($number < 100) {
            $remainder = $number % 10;

            return $remainder
                ? $this->tens[intdiv($number, 10)].'-'.$this->ones[$remainder]
                : $this->tens[intdiv($number, 10)];
        }

        if ($number < 1000) {
            $remainder = $number % 100;

            return trim($this->ones[intdiv($number, 100)].' Hundred '.($remainder ? $this->numberToWords($remainder) : ''));
        }

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $remainder = $number % $value;

                return trim($this->numberToWords(intdiv($number, $value))." $label ".($remainder ? $this->numberToWords($remainder) : ''));
            }
        }

        return '';
    }
}
