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
        Schema::table('owners', function (Blueprint $table) {
            // 🌟 1. Ensure telegram_chat_id can safely fall back to null
            $table->string('telegram_chat_id')->nullable()->change();

            // 🌟 2. Add our unique temporary setup token column
            $table->string('telegram_verification_token')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn('telegram_verification_token');
        });
    }
};
