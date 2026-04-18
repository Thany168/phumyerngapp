<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Owner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@shop.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        // Owner user
        $ownerUser = User::create([
            'name'              => 'Demo Owner',
            'email'             => 'owner@shop.com',
            'telegram_id'       => '111111111',
            'telegram_username' => 'demoowner',
            'password'          => Hash::make('password'),
            'role'              => 'owner',
        ]);

        $owner = Owner::create([
            'user_id'          => $ownerUser->id,
            'shop_name'        => 'Demo Shop',
            'shop_description' => 'Best shop in town',
            'telegram_chat_id' => '111111111',
            'status'           => 'active',
        ]);

        Subscription::create([
            'owner_id'   => $owner->id,
            'plan'       => 'pro',
            'status'     => 'active',
            'starts_at'  => now(),
            'expires_at' => now()->addYear(),
            'amount_paid' => 99.00,
        ]);

        // Categories
        $cat1 = Category::create([
            'owner_id'   => $owner->id,
            'name'       => 'Drinks',
            'sort_order' => 1,
            'is_active'  => true,
        ]);

        $cat2 = Category::create([
            'owner_id'   => $owner->id,
            'name'       => 'Food',
            'sort_order' => 2,
            'is_active'  => true,
        ]);

        // Products
        Product::create([
            'owner_id'     => $owner->id,
            'category_id'  => $cat1->id,
            'name'         => 'Iced Coffee',
            'price'        => 3.50,
            'stock'        => -1,
            'is_available' => true,
            'sort_order'   => 1,
        ]);

        Product::create([
            'owner_id'     => $owner->id,
            'category_id'  => $cat1->id,
            'name'         => 'Lemon Tea',
            'price'        => 2.00,
            'stock'        => -1,
            'is_available' => true,
            'sort_order'   => 2,
        ]);

        Product::create([
            'owner_id'     => $owner->id,
            'category_id'  => $cat2->id,
            'name'         => 'Fried Rice',
            'price'        => 5.00,
            'stock'        => 20,
            'is_available' => true,
            'sort_order'   => 1,
        ]);

        Product::create([
            'owner_id'     => $owner->id,
            'category_id'  => $cat2->id,
            'name'         => 'Spring Rolls',
            'price'        => 3.00,
            'stock'        => 15,
            'is_available' => true,
            'sort_order'   => 2,
        ]);

        // Delivery staff
        User::create([
            'name'              => 'Delivery Staff 1',
            'email'             => 'delivery1@shop.com',
            'telegram_id'       => '222222222',
            'telegram_username' => 'deliverystaff1',
            'password'          => Hash::make('password'),
            'role'              => 'delivery',
        ]);

        // Test customer
        User::create([
            'name'              => 'Test Customer',
            'email'             => 'customer@shop.com',
            'telegram_id'       => '333333333',
            'telegram_username' => 'testcustomer',
            'password'          => Hash::make('password'),
            'role'              => 'customer',
        ]);
    }
}
