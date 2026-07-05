<?php

namespace Tests\Unit;

use App\Support\QuantityFormatter;
use PHPUnit\Framework\TestCase;

class QuantityFormatterTest extends TestCase
{
    public function test_whole_numbers_without_decimals(): void
    {
        $this->assertSame('2', QuantityFormatter::format(2));
        $this->assertSame('2', QuantityFormatter::format('2.00'));
        $this->assertSame('200', QuantityFormatter::format(200));
    }

    public function test_fractional_quantities_trim_trailing_zeros(): void
    {
        $this->assertSame('2,5', QuantityFormatter::format(2.5));
        $this->assertSame('2.5', QuantityFormatter::format(2.5, '.'));
    }
}
