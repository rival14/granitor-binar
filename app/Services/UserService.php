<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewUserAdminNotification;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UserService
{
    /**
     * Retrieve a paginated, filtered list of active users.
     *
     * Each user in the result includes `orders_count` and `can_edit`
     * relative to the currently authenticated user.
     */
    public function list(array $filters, User $authenticatedUser): LengthAwarePaginator
    {
        Log::info('[UserService@list] Fetching users', [
            'filters' => $filters,
            'requested_by' => $authenticatedUser->id,
        ]);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $perPage = $filters['per_page'] ?? 15;

        $users = User::query()
            ->active()
            ->search($filters['search'] ?? null)
            ->withCount('orders')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);

        // Attach the `can_edit` permission flag to each user model
        $this->attachCanEditFlag($users->getCollection(), $authenticatedUser);

        return $users;
    }

    /**
     * Create a new user and send registration emails.
     */
    public function create(array $data): User
    {
        Log::info('[UserService@create] Creating user', ['email' => $data['email']]);

        $user = DB::transaction(function () use ($data) {
            return User::create($data);
        });

        // Refresh to load database defaults (e.g. role, is_active)
        $user->refresh();

        $this->sendRegistrationNotifications($user);

        Log::info('[UserService@create] User created successfully', ['user_id' => $user->id]);

        return $user;
    }

    /**
     * Update an existing user with the provided data.
     */
    public function update(User $user, array $data): User
    {
        Log::info('[UserService@update] Updating user', ['user_id' => $user->id]);

        $user->update($data);

        return $user->refresh();
    }

    /**
     * Soft-delete a user.
     */
    public function delete(User $user): void
    {
        Log::info('[UserService@delete] Deleting user', ['user_id' => $user->id]);

        $user->delete();
    }

    /**
     * Find a user by ID or fail with 404.
     */
    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Send confirmation email to the user and notification to the admin.
     */
    private function sendRegistrationNotifications(User $user): void
    {
        $user->notify(new UserRegisteredNotification($user));

        $adminEmail = config('mail.admin_address');
        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new NewUserAdminNotification($user));
        }
    }

    /**
     * Attach a `can_edit` boolean flag to each user in the collection
     * based on the authenticated user's role and the UserPolicy.
     */
    private function attachCanEditFlag($users, User $authenticatedUser): void
    {
        $users->each(function (User $user) use ($authenticatedUser) {
            $user->can_edit = $authenticatedUser->can('update', $user);
        });
    }
}
