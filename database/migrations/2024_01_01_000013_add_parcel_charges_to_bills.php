<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('items_subtotal', 10, 2)->default(0)->after('grand_total');
            $table->decimal('parcel_charges_total', 10, 2)->default(0)->after('items_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['items_subtotal', 'parcel_charges_total']);
        });
    }
};
