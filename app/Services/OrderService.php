<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Retrieve a paginated list of orders for the given user.
     *
     * Eager-loads items and products to avoid N+1 queries.
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        Log::info('[OrderService@list] Fetching orders', [
            'user_id' => $user->id,
            'filters' => $filters,
        ]);

        $perPage = $filters['per_page'] ?? 15;

        return Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Submit a new order for the authenticated user.
     *
     * Validates stock availability, creates order with items,
     * calculates total, and deducts stock — all within a transaction.
     *
     * @throws ValidationException when a product has insufficient stock
     */
    public function submit(User $user, array $data): Order
    {
        Log::info('[OrderService@submit] Submitting order', ['user_id' => $user->id]);

        $order = DB::transaction(function () use ($user, $data) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalAmount = $this->createItemsAndCalculateTotal($order, $data['items']);

            $order->update(['total_amount' => $totalAmount]);

            return $order;
        });

        $order->load('items.product');

        Log::info('[OrderService@submit] Order submitted', [
            'order_id' => $order->id,
            'total_amount' => $order->total_amount,
            'items_count' => $order->items->count(),
        ]);

        return $order;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Create order items from the input, validate stock, deduct quantities,
     * and return the computed total amount.
     *
     * @throws ValidationException
     */
    private function createItemsAndCalculateTotal(Order $order, array $items): string
    {
        $totalAmount = '0.00';

        // Batch-load products to avoid N+1 queries
        $productIds = collect($items)->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);

            $this->ensureSufficientStock($product, $item['quantity']);

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
            ]);

            $product->decrement('stock_quantity', $item['quantity']);

            $lineTotal = bcmul($product->price, (string) $item['quantity'], 2);
            $totalAmount = bcadd($totalAmount, $lineTotal, 2);
        }

        return $totalAmount;
    }

    /**
     * Ensure the product has enough stock for the requested quantity.
     *
     * @throws ValidationException
     */
    private function ensureSufficientStock(Product $product, int $quantity): void
    {
        if (! $product->hasStock($quantity)) {
            throw ValidationException::withMessages([
                'items' => "Insufficient stock for product \"{$product->name}\" (available: {$product->stock_quantity}, requested: {$quantity}).",
            ]);
        }
    }
}
