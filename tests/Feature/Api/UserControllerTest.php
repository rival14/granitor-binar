<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewUserAdminNotification;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->manager = User::factory()->manager()->create();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    #[Test]
    public function index_returns_only_active_users_with_pagination(): void
    {
        User::factory()->inactive()->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'is_active', 'orders_count', 'can_edit']],
                'links',
                'meta',
            ]);

        // 3 from setUp + 0 inactive = 3 active users
        $response->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function index_excludes_password_from_response(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $response->assertOk();

        foreach ($response->json('data') as $userData) {
            $this->assertArrayNotHasKey('password', $userData);
            $this->assertArrayNotHasKey('remember_token', $userData);
        }
    }

    #[Test]
    public function index_includes_orders_count(): void
    {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $userData = collect($response->json('data'))->firstWhere('id', $this->user->id);
        $this->assertSame(3, $userData['orders_count']);
    }

    #[Test]
    public function index_includes_can_edit_flag_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        // Admin can edit anyone
        foreach ($response->json('data') as $userData) {
            $this->assertTrue($userData['can_edit']);
        }
    }

    #[Test]
    public function index_can_edit_flag_respects_manager_rules(): void
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/users');

        $users = collect($response->json('data'));

        // Manager can only edit regular users
        $this->assertFalse($users->firstWhere('id', $this->admin->id)['can_edit']);
        $this->assertFalse($users->firstWhere('id', $this->manager->id)['can_edit']);
        $this->assertTrue($users->firstWhere('id', $this->user->id)['can_edit']);
    }

    #[Test]
    public function index_can_edit_flag_respects_user_rules(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/users');

        $users = collect($response->json('data'));

        // Regular user can only edit themselves
        $this->assertTrue($users->firstWhere('id', $this->user->id)['can_edit']);
        $this->assertFalse($users->firstWhere('id', $this->admin->id)['can_edit']);
    }

    #[Test]
    public function index_searches_by_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?search=' . urlencode($this->user->name));

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($this->user->id, $response->json('data.0.id'));
    }

    #[Test]
    public function index_searches_by_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?search=' . urlencode($this->admin->email));

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    #[Test]
    public function index_sorts_by_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?sort_by=name&sort_direction=asc');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names);
    }

    #[Test]
    public function index_paginates_results(): void
    {
        User::factory()->count(10)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?per_page=5&page=1');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(13, $response->json('meta.total')); // 3 setUp + 10
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    #[Test]
    public function store_creates_user_and_returns_201(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.email', 'newuser@example.com')
            ->assertJsonPath('data.role', 'user');

        // Password must not be in response
        $this->assertArrayNotHasKey('password', $response->json('data'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    #[Test]
    public function store_sends_confirmation_email_to_user(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'Mail Test',
                'email' => 'mailtest@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

        Notification::assertSentTo(
            User::where('email', 'mailtest@example.com')->first(),
            UserRegisteredNotification::class,
        );
    }

    #[Test]
    public function store_sends_notification_email_to_admin(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'Notify Test',
                'email' => 'notify@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

        Notification::assertSentOnDemand(NewUserAdminNotification::class);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function store_validates_unique_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'Duplicate',
                'email' => $this->user->email,
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function store_validates_password_confirmation(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'No Confirm',
                'email' => 'noconfirm@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'DifferentPass!',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function store_allows_specifying_role(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'New Manager',
                'email' => 'mgr@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
                'role' => 'manager',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'manager');
    }

    #[Test]
    public function store_rejects_invalid_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'Bad Role',
                'email' => 'badrole@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
                'role' => 'superadmin',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    #[Test]
    public function show_returns_single_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/users/{$this->user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.name', $this->user->name);
    }

    #[Test]
    public function show_returns_404_for_nonexistent_user(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/users/99999')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_can_update_any_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/users/{$this->user->id}", ['name' => 'Updated Name']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'Updated Name']);
    }

    #[Test]
    public function user_can_update_themselves(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/users/{$this->user->id}", ['name' => 'Self Update']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Self Update');
    }

    #[Test]
    public function user_cannot_update_other_users(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)
            ->putJson("/api/users/{$other->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    #[Test]
    public function manager_can_update_regular_users(): void
    {
        $response = $this->actingAs($this->manager)
            ->putJson("/api/users/{$this->user->id}", ['name' => 'Manager Edit']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Manager Edit');
    }

    #[Test]
    public function manager_cannot_update_admin(): void
    {
        $this->actingAs($this->manager)
            ->putJson("/api/users/{$this->admin->id}", ['name' => 'Nope'])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    #[Test]
    public function admin_can_delete_other_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->user->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted($this->user);
    }

    #[Test]
    public function admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}")
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_delete_users(): void
    {
        $this->actingAs($this->manager)
            ->deleteJson("/api/users/{$this->user->id}")
            ->assertForbidden();
    }

    #[Test]
    public function regular_user_cannot_delete_users(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/users/{$other->id}")
            ->assertForbidden();
    }
}
