<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy();
    }

    // -------------------------------------------------------------------------
    // Update policy
    // -------------------------------------------------------------------------

    #[Test]
    public function administrator_can_update_any_user(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();
        $regularUser = User::factory()->create();

        $this->assertTrue($this->policy->update($admin, $admin));
        $this->assertTrue($this->policy->update($admin, $manager));
        $this->assertTrue($this->policy->update($admin, $regularUser));
    }

    #[Test]
    public function manager_can_update_only_regular_users(): void
    {
        $manager = User::factory()->manager()->create();
        $regularUser = User::factory()->create();
        $anotherManager = User::factory()->manager()->create();
        $admin = User::factory()->administrator()->create();

        $this->assertTrue($this->policy->update($manager, $regularUser));
        $this->assertFalse($this->policy->update($manager, $anotherManager));
        $this->assertFalse($this->policy->update($manager, $admin));
    }

    #[Test]
    public function manager_cannot_update_themselves(): void
    {
        $manager = User::factory()->manager()->create();

        // Manager role is "manager", not "user", so they can't edit themselves via this rule
        $this->assertFalse($this->policy->update($manager, $manager));
    }

    #[Test]
    public function regular_user_can_update_only_themselves(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertTrue($this->policy->update($user, $user));
        $this->assertFalse($this->policy->update($user, $otherUser));
    }

    // -------------------------------------------------------------------------
    // Delete policy
    // -------------------------------------------------------------------------

    #[Test]
    public function administrator_can_delete_other_users(): void
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->assertTrue($this->policy->delete($admin, $user));
        $this->assertTrue($this->policy->delete($admin, $manager));
    }

    #[Test]
    public function administrator_cannot_delete_themselves(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->assertFalse($this->policy->delete($admin, $admin));
    }

    #[Test]
    public function manager_cannot_delete_anyone(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create();

        $this->assertFalse($this->policy->delete($manager, $user));
    }

    #[Test]
    public function regular_user_cannot_delete_anyone(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertFalse($this->policy->delete($user, $otherUser));
        $this->assertFalse($this->policy->delete($user, $user));
    }
}
