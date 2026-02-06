<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with sample data for development.
     */
    public function run(): void
    {
        // Create an admin user with a known API token for testing
        $admin = User::factory()->administrator()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->createToken('admin-token');

        // Create a manager
        $manager = User::factory()->manager()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
        ]);
        $manager->createToken('manager-token');

        // Create regular users
        $users = User::factory(8)->create();

        // Create an inactive user for testing filter behavior
        User::factory()->inactive()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
        ]);

        // Create products
        Product::factory(20)->create();

        // Create some orders for existing users
        $allUsers = collect([$admin, $manager])->merge($users);
        $allUsers->each(function (User $user) {
            Order::factory(rand(0, 3))->create(['user_id' => $user->id]);
        });
    }
}
