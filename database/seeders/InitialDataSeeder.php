<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ExpenseCategory;
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

        // Default staff account
        User::firstOrCreate(
            ['email' => 'staff@wjcafe.com'],
            [
                'name'     => 'Cafe Staff',
                'password' => Hash::make('admin@123'),
                'role'     => UserRole::Staff,
            ]
        );

        // Default categories
        foreach (['Tea', 'Coffee', 'Juices', 'Food'] as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Default expense categories
        foreach (['Salary', 'Rent', 'Electricity', 'Gas', 'Maintenance', 'Miscellaneous'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Default tables
        for ($i = 1; $i <= 10; $i++) {
            Table::firstOrCreate(['table_number' => $i], ['status' => 'vacant']);
        }
    }
}
