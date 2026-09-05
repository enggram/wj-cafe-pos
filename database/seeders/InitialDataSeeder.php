<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Table;
use Illuminate\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    /**
     * Seed initial data required for the POS system to operate.
     * This includes default categories and table configurations.
     */
    public function run(): void
    {
        // Create default categories (Requirement 1.7)
        $categories = ['Tea', 'Coffee', 'Juices', 'Food'];

        foreach ($categories as $name) {
            Category::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }

        // Create default tables (10 tables for the cafe)
        for ($i = 1; $i <= 10; $i++) {
            Table::firstOrCreate(
                ['table_number' => $i],
                ['status' => 'vacant']
            );
        }
    }
}
