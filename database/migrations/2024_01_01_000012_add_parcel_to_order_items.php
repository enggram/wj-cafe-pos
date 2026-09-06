<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_parcel')->default(false)->after('unit_price');
            $table->decimal('parcel_rate', 8, 2)->default(0.00)->after('is_parcel');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['is_parcel', 'parcel_rate']);
        });
    }
};
