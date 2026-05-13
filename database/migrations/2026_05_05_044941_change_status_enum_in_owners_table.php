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
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE owners DROP CONSTRAINT owners_status_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_status_check CHECK (status IN ('active', 'suspended', 'trial'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE owners DROP CONSTRAINT owners_status_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE owners ADD CONSTRAINT owners_status_check CHECK (status IN ('active', 'suspended', 'trail'))");
    }
};
