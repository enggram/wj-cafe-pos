<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_entries', function (Blueprint $table) {
            $table->id();
            $table->string('item_name', 100);
            $table->decimal('quantity', 10, 3);
            $table->decimal('cost', 10, 2);
            $table->date('purchase_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_entries');
    }
};
