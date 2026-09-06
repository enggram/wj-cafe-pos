<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('description', 255)->nullable();
            $table->date('expense_date');
            $table->timestamps();

            $table->index('expense_date');
            $table->index(['expense_category_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_entries');
    }
};
