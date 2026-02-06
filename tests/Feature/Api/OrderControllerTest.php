<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function store_creates_order_with_items_and_returns_201(): void
    {
        $productA = Product::factory()->create(['price' => '25.00', 'stock_quantity' => 50]);
        $productB = Product::factory()->create(['price' => '10.00', 'stock_quantity' => 30]);

        $payload = [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'quantity' => 3],
            ],
            'notes' => 'Please wrap carefully',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '80.00')
            ->assertJsonPath('data.notes', 'Please wrap carefully')
            ->assertJsonCount(2, 'data.items');
    }

    #[Test]
    public function store_deducts_product_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 20]);

        $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ]);

        $this->assertSame(15, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_snapshots_unit_price_from_product(): void
    {
        $product = Product::factory()->create(['price' => '99.99', 'stock_quantity' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $response->assertCreated();
        $this->assertSame('99.99', $response->json('data.items.0.unit_price'));
    }

    #[Test]
    public function store_fails_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        // Stock should remain unchanged
        $this->assertSame(2, $product->fresh()->stock_quantity);

        // No order should have been created
        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function store_validates_items_are_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function store_validates_items_must_not_be_empty(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', ['items' => []]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function store_validates_product_must_exist(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => 99999, 'quantity' => 1]],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    #[Test]
    public function store_validates_quantity_must_be_positive(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 0]],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $this->postJson('/api/orders', ['items' => []])
            ->assertUnauthorized();
    }

    #[Test]
    public function store_includes_product_data_in_response(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'product_id', 'quantity', 'unit_price', 'line_total', 'product'],
                    ],
                ],
            ]);
    }
}
