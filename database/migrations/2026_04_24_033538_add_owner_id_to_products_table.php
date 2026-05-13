<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('products', function (Blueprint $table) {
        // This adds the owner_id column and links it to the owners table
        //$table->foreignId('owner_id')->after('id')->constrained('owners')->onDelete('cascade');
    });
}

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
};
