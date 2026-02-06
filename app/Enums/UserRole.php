<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case User = 'user';

    /**
     * Check if this role has a higher privilege than the given role.
     */
    public function outranks(self $other): bool
    {
        return self::rankOf($this) > self::rankOf($other);
    }

    /**
     * Check if this role is at least as privileged as the given role.
     */
    public function isAtLeast(self $role): bool
    {
        return self::rankOf($this) >= self::rankOf($role);
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Manager => 'Manager',
            self::User => 'User',
        };
    }

    /**
     * Return numeric rank for privilege comparison.
     * Higher number = higher privilege.
     */
    private static function rankOf(self $role): int
    {
        return match ($role) {
            self::Administrator => 3,
            self::Manager => 2,
            self::User => 1,
        };
    }
}
