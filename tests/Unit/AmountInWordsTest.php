<?php

namespace Tests\Unit;

use App\Services\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_whole_bdt_amount_uses_taka_without_zero(): void
    {
        $words = new AmountInWords;

        $this->assertSame(
            'One Lakh Twelve Thousand Two Hundred Twenty Taka Only',
            $words->convert(112220)
        );
    }

    public function test_bdt_amount_with_paisa(): void
    {
        $words = new AmountInWords;

        $this->assertSame(
            'Twenty-Three Thousand Three Hundred Twenty-Nine Taka and Ninety-Six Paisa Only',
            $words->convert(23329.96)
        );
    }

    public function test_large_bdt_amount_uses_lakh_and_crore(): void
    {
        $words = new AmountInWords;

        $this->assertSame(
            'One Crore Twenty-Three Lakh Forty-Five Thousand Six Hundred Seventy-Eight Taka Only',
            $words->convert(12345678)
        );
    }
}
