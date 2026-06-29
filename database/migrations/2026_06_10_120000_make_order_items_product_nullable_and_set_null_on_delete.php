<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY product_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN product_id DROP NOT NULL');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('order_items_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable();
                $table->string('product_name');
                $table->decimal('unit_price', 10, 2);
                $table->integer('quantity');
                $table->decimal('subtotal', 10, 2);
                $table->timestamps();
            });

            DB::statement('INSERT INTO order_items_temp (id, order_id, product_id, product_name, unit_price, quantity, subtotal, created_at, updated_at) SELECT id, order_id, product_id, product_name, unit_price, quantity, subtotal, created_at, updated_at FROM order_items');

            Schema::drop('order_items');
            Schema::rename('order_items_temp', 'order_items');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE order_items MODIFY product_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN product_id SET NOT NULL');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('order_items_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->string('product_name');
                $table->decimal('unit_price', 10, 2);
                $table->integer('quantity');
                $table->decimal('subtotal', 10, 2);
                $table->timestamps();
            });

            DB::statement('INSERT INTO order_items_temp (id, order_id, product_id, product_name, unit_price, quantity, subtotal, created_at, updated_at) SELECT id, order_id, product_id, product_name, unit_price, quantity, subtotal, created_at, updated_at FROM order_items');

            Schema::drop('order_items');
            Schema::rename('order_items_temp', 'order_items');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });
        }
    }
};
