<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Make customer_phone nullable — phone is now optional at checkout
            $table->string('customer_phone', 30)->nullable()->change();

            // Make delivery_location nullable — location is now optional at checkout
            $table->string('delivery_location')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert back to NOT NULL (will fail if existing rows have nulls)
            $table->string('customer_phone', 30)->nullable(false)->change();
            $table->string('delivery_location')->nullable(false)->change();
        });
    }
};
