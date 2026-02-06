<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function is_administrator_returns_true_for_admin_role(): void
    {
        $user = User::factory()->administrator()->create();

        $this->assertTrue($user->isAdministrator());
        $this->assertFalse($user->isManager());
    }

    #[Test]
    public function is_manager_returns_true_for_manager_role(): void
    {
        $user = User::factory()->manager()->create();

        $this->assertTrue($user->isManager());
        $this->assertFalse($user->isAdministrator());
    }

    #[Test]
    public function regular_user_is_neither_admin_nor_manager(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAdministrator());
        $this->assertFalse($user->isManager());
        $this->assertSame(UserRole::User, $user->role);
    }

    #[Test]
    public function active_scope_excludes_inactive_users(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->inactive()->create();

        $this->assertCount(3, User::active()->get());
    }

    #[Test]
    public function search_scope_finds_by_name(): void
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $results = User::search('John')->get();

        $this->assertCount(1, $results);
        $this->assertSame('John Doe', $results->first()->name);
    }

    #[Test]
    public function search_scope_finds_by_email(): void
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $results = User::search('jane@')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Jane Smith', $results->first()->name);
    }

    #[Test]
    public function search_scope_returns_all_when_term_is_null(): void
    {
        User::factory()->count(3)->create();

        $this->assertCount(3, User::search(null)->get());
    }

    #[Test]
    public function search_scope_returns_all_when_term_is_empty(): void
    {
        User::factory()->count(3)->create();

        $this->assertCount(3, User::search('')->get());
    }

    #[Test]
    public function user_has_orders_relationship(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->orders());
    }

    #[Test]
    public function password_is_hidden_in_serialization(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    #[Test]
    public function role_is_cast_to_user_role_enum(): void
    {
        $user = User::factory()->create(['role' => 'administrator']);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertSame(UserRole::Administrator, $user->role);
    }
}
