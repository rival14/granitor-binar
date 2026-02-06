<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertSame('pending', OrderStatus::Pending->value);
        $this->assertSame('confirmed', OrderStatus::Confirmed->value);
        $this->assertSame('processing', OrderStatus::Processing->value);
        $this->assertSame('shipped', OrderStatus::Shipped->value);
        $this->assertSame('delivered', OrderStatus::Delivered->value);
        $this->assertSame('cancelled', OrderStatus::Cancelled->value);
    }

    #[Test]
    public function it_returns_human_readable_labels(): void
    {
        $this->assertSame('Pending', OrderStatus::Pending->label());
        $this->assertSame('Confirmed', OrderStatus::Confirmed->label());
        $this->assertSame('Processing', OrderStatus::Processing->label());
        $this->assertSame('Shipped', OrderStatus::Shipped->label());
        $this->assertSame('Delivered', OrderStatus::Delivered->label());
        $this->assertSame('Cancelled', OrderStatus::Cancelled->label());
    }

    #[Test]
    public function delivered_and_cancelled_are_final(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
    }

    #[Test]
    public function non_terminal_statuses_are_not_final(): void
    {
        $this->assertFalse(OrderStatus::Pending->isFinal());
        $this->assertFalse(OrderStatus::Confirmed->isFinal());
        $this->assertFalse(OrderStatus::Processing->isFinal());
        $this->assertFalse(OrderStatus::Shipped->isFinal());
    }
}
