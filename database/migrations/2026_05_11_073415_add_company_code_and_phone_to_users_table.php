<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_code')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->unique()->after('company_code');

            // make email nullable since owners won't use it
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_code', 'phone']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
