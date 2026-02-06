<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * GET /api/orders
     *
     * List orders for the authenticated user with pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderService->list(
            user: $request->user(),
            filters: $request->all(),
        );

        return OrderResource::collection($orders);
    }

    /**
     * POST /api/orders
     *
     * Submit a new order for the authenticated user.
     * Validates stock availability and creates the order atomically.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->submit(
            user: $request->user(),
            data: $request->validated(),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}
