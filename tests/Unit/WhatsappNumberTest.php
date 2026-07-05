<?php

namespace Tests\Unit;

use App\Support\WhatsappNumber;
use PHPUnit\Framework\TestCase;

class WhatsappNumberTest extends TestCase
{
    public function test_normalizes_leading_zero_to_62(): void
    {
        $this->assertSame('628123456789', WhatsappNumber::normalize('08123456789'));
    }

    public function test_keeps_62_prefix(): void
    {
        $this->assertSame('628123456789', WhatsappNumber::normalize('628123456789'));
    }

    public function test_normalizes_local_eight_prefix(): void
    {
        $this->assertSame('628123456789', WhatsappNumber::normalize('8123456789'));
    }
}
