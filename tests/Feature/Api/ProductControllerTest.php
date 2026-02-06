<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->administrator()->create();
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    #[Test]
    public function index_returns_paginated_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'sku', 'price', 'stock_quantity', 'is_active']],
                'meta',
            ])
            ->assertJsonPath('meta.total', 5);
    }

    #[Test]
    public function index_paginates_with_per_page(): void
    {
        Product::factory()->count(10)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?per_page=3');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/products')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    #[Test]
    public function store_creates_product_and_returns_201(): void
    {
        $payload = [
            'name' => 'Widget Pro',
            'description' => 'A professional widget',
            'sku' => 'WDG-PRO-001',
            'price' => 49.99,
            'stock_quantity' => 100,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/products', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Widget Pro')
            ->assertJsonPath('data.sku', 'WDG-PRO-001')
            ->assertJsonPath('data.price', '49.99');

        $this->assertDatabaseHas('products', ['sku' => 'WDG-PRO-001']);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/products', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'price']);
    }

    #[Test]
    public function store_validates_unique_sku(): void
    {
        Product::factory()->create(['sku' => 'DUPE-SKU']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/products', [
                'name' => 'Dupe',
                'sku' => 'DUPE-SKU',
                'price' => 10.00,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    #[Test]
    public function store_validates_price_is_numeric(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/products', [
                'name' => 'Bad Price',
                'sku' => 'BAD-001',
                'price' => 'not-a-number',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    #[Test]
    public function show_returns_single_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_product(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/products/99999')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    #[Test]
    public function update_modifies_product(): void
    {
        $product = Product::factory()->create(['price' => '10.00']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Updated Widget',
                'price' => 19.99,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Widget')
            ->assertJsonPath('data.price', '19.99');
    }

    #[Test]
    public function update_validates_unique_sku_ignoring_self(): void
    {
        $product = Product::factory()->create(['sku' => 'KEEP-SKU']);
        Product::factory()->create(['sku' => 'TAKEN-SKU']);

        // Updating to own SKU should work
        $this->actingAs($this->admin)
            ->putJson("/api/products/{$product->id}", ['sku' => 'KEEP-SKU'])
            ->assertOk();

        // Updating to another product's SKU should fail
        $this->actingAs($this->admin)
            ->putJson("/api/products/{$product->id}", ['sku' => 'TAKEN-SKU'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    #[Test]
    public function destroy_soft_deletes_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted($product);
    }
}
