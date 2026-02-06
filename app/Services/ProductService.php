<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    /**
     * Retrieve a paginated list of products.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        return Product::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new product.
     */
    public function create(array $data): Product
    {
        Log::info('[ProductService@create] Creating product', ['sku' => $data['sku']]);

        $product = DB::transaction(function () use ($data) {
            return Product::create($data);
        });

        $product->refresh();

        Log::info('[ProductService@create] Product created', ['product_id' => $product->id]);

        return $product;
    }

    /**
     * Update an existing product with the provided data.
     */
    public function update(Product $product, array $data): Product
    {
        Log::info('[ProductService@update] Updating product', ['product_id' => $product->id]);

        $product->update($data);

        return $product->refresh();
    }

    /**
     * Soft-delete a product.
     */
    public function delete(Product $product): void
    {
        Log::info('[ProductService@delete] Deleting product', ['product_id' => $product->id]);

        $product->delete();
    }

    /**
     * Find a product by ID or fail with 404.
     */
    public function findOrFail(int $id): Product
    {
        return Product::findOrFail($id);
    }
}
