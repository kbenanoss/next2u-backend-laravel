<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $shopOwnerRole = Role::create(['name' => 'shop_owner']);
        $agentRole = Role::create(['name' => 'agent']);

        // Create users and assign roles
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->roles()->attach($adminRole);

        $shopOwner = User::create([
            'name' => 'Shop Owner',
            'email' => 'shopowner@example.com',
            'password' => Hash::make('password'),
        ]);
        $shopOwner->roles()->attach($shopOwnerRole);

        $agent = User::create([
            'name' => 'Agent User',
            'email' => 'agent@example.com',
            'password' => Hash::make('password'),
        ]);
        $agent->roles()->attach($agentRole);
    }
}
