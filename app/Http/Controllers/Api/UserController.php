<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * GET /api/users
     *
     * List active users with search, sorting, pagination,
     * orders_count, and can_edit permission flag.
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $users = $this->userService->list(
            filters: $request->validated(),
            authenticatedUser: $request->user(),
        );

        return UserResource::collection($users);
    }

    /**
     * POST /api/users
     *
     * Create a new user and send registration emails.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/users/{user}
     *
     * Show a single user's details.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * PUT /api/users/{user}
     *
     * Update an existing user. Authorization handled via UpdateUserRequest.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->userService->update($user, $request->validated());

        return new UserResource($user);
    }

    /**
     * DELETE /api/users/{user}
     *
     * Soft-delete a user. Only administrators may delete.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return response()->json(['message' => 'User deleted successfully.'], 200);
    }
}
