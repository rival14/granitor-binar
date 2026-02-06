<?php

namespace Tests\Unit\Models;

use App\Models\OrderItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderItemTest extends TestCase
{
    #[Test]
    public function line_total_calculates_correctly(): void
    {
        $item = new OrderItem([
            'unit_price' => '25.50',
            'quantity' => 3,
        ]);

        $this->assertSame('76.50', $item->line_total);
    }

    #[Test]
    public function line_total_handles_single_quantity(): void
    {
        $item = new OrderItem([
            'unit_price' => '99.99',
            'quantity' => 1,
        ]);

        $this->assertSame('99.99', $item->line_total);
    }

    #[Test]
    public function line_total_handles_zero_price(): void
    {
        $item = new OrderItem([
            'unit_price' => '0.00',
            'quantity' => 5,
        ]);

        $this->assertSame('0.00', $item->line_total);
    }
}
