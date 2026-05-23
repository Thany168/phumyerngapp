<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('owners', function (Blueprint $table) {
        // 🌟 Only add "telegram_bot_token" if it doesn't exist yet
        if (!Schema::hasColumn('owners', 'telegram_bot_token')) {
            $table->string('telegram_bot_token')->nullable();
        }

        // 🌟 Add the missing bot username field securely
        if (!Schema::hasColumn('owners', 'telegram_bot_username')) {
            $table->string('telegram_bot_username')->nullable();
        }
    });
}

public function down()
{
    Schema::table('owners', function (Blueprint $table) {
        $table->dropColumn(['telegram_bot_token', 'telegram_bot_username']);
    });
}
};
