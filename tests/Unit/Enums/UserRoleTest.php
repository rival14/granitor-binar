<?php

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    #[Test]
    public function it_has_correct_string_values(): void
    {
        $this->assertSame('administrator', UserRole::Administrator->value);
        $this->assertSame('manager', UserRole::Manager->value);
        $this->assertSame('user', UserRole::User->value);
    }

    #[Test]
    public function it_returns_human_readable_labels(): void
    {
        $this->assertSame('Administrator', UserRole::Administrator->label());
        $this->assertSame('Manager', UserRole::Manager->label());
        $this->assertSame('User', UserRole::User->label());
    }

    #[Test]
    public function administrator_outranks_manager_and_user(): void
    {
        $this->assertTrue(UserRole::Administrator->outranks(UserRole::Manager));
        $this->assertTrue(UserRole::Administrator->outranks(UserRole::User));
    }

    #[Test]
    public function manager_outranks_user_but_not_administrator(): void
    {
        $this->assertTrue(UserRole::Manager->outranks(UserRole::User));
        $this->assertFalse(UserRole::Manager->outranks(UserRole::Administrator));
    }

    #[Test]
    public function user_outranks_nobody(): void
    {
        $this->assertFalse(UserRole::User->outranks(UserRole::Manager));
        $this->assertFalse(UserRole::User->outranks(UserRole::Administrator));
    }

    #[Test]
    public function no_role_outranks_itself(): void
    {
        $this->assertFalse(UserRole::Administrator->outranks(UserRole::Administrator));
        $this->assertFalse(UserRole::Manager->outranks(UserRole::Manager));
        $this->assertFalse(UserRole::User->outranks(UserRole::User));
    }

    #[Test]
    public function is_at_least_checks_privilege_level(): void
    {
        // Administrator is at least everything
        $this->assertTrue(UserRole::Administrator->isAtLeast(UserRole::Administrator));
        $this->assertTrue(UserRole::Administrator->isAtLeast(UserRole::Manager));
        $this->assertTrue(UserRole::Administrator->isAtLeast(UserRole::User));

        // Manager is at least manager and user, but not admin
        $this->assertFalse(UserRole::Manager->isAtLeast(UserRole::Administrator));
        $this->assertTrue(UserRole::Manager->isAtLeast(UserRole::Manager));
        $this->assertTrue(UserRole::Manager->isAtLeast(UserRole::User));

        // User is only at least user
        $this->assertFalse(UserRole::User->isAtLeast(UserRole::Administrator));
        $this->assertFalse(UserRole::User->isAtLeast(UserRole::Manager));
        $this->assertTrue(UserRole::User->isAtLeast(UserRole::User));
    }
}
