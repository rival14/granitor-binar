<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function has_stock_returns_true_when_quantity_is_sufficient(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->assertTrue($product->hasStock(5));
        $this->assertTrue($product->hasStock(10));
    }

    #[Test]
    public function has_stock_returns_false_when_quantity_is_insufficient(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->assertFalse($product->hasStock(4));
        $this->assertFalse($product->hasStock(100));
    }

    #[Test]
    public function has_stock_returns_true_for_zero_quantity_request(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 0]);

        $this->assertTrue($product->hasStock(0));
    }

    #[Test]
    public function active_scope_excludes_inactive_products(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->inactive()->create();

        $this->assertCount(3, Product::active()->get());
    }

    #[Test]
    public function product_can_be_soft_deleted(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertCount(0, Product::all());
        $this->assertCount(1, Product::withTrashed()->get());
    }
}
