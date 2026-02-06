<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Authorization policy for User operations.
 *
 * Rules:
 *  - Administrator → can edit any user
 *  - Manager       → can edit only users with role "user"
 *  - User          → can edit only themselves
 */
class UserPolicy
{
    /**
     * Determine if the authenticated user can update the target user.
     */
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        return match ($authenticatedUser->role) {
            UserRole::Administrator => true,
            UserRole::Manager => $targetUser->role === UserRole::User,
            UserRole::User => $authenticatedUser->id === $targetUser->id,
        };
    }

    /**
     * Determine if the authenticated user can delete the target user.
     */
    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        // Only administrators can delete users; nobody deletes themselves
        return $authenticatedUser->isAdministrator()
            && $authenticatedUser->id !== $targetUser->id;
    }
}
