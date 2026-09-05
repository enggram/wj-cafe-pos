<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // Default admin account (change the password after first login)
        User::firstOrCreate(
            ['email' => 'admin@wjcafe.com'],
            [
                'name'     => 'Cafe Admin',
                'password' => Hash::make('Admin@1235'),
                'role'     => UserRole::Admin,
            ]
        );

        // Default categories
        foreach (['Tea', 'Coffee', 'Juices', 'Food'] as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Default tables
        for ($i = 1; $i <= 10; $i++) {
            Table::firstOrCreate(['table_number' => $i], ['status' => 'vacant']);
        }
    }
}
