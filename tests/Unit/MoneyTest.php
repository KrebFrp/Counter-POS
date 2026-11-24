<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_is_converted_without_rounding(): void
    {
        $this->assertSame(12550, Money::cents('125.50'));
        $this->assertSame(10, Money::cents('0.1'));
        $this->assertSame(0, Money::cents('0'));
        $this->assertSame('376.50', Money::decimal(Money::cents('125.50') * 3));
        $this->assertSame(9999999999, Money::cents('99999999.99'));
        $this->assertSame('0.00', Money::decimal(0));
    }

    public function test_sub_cent_values_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::cents('1.001');
    }
}
