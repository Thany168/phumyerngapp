<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OwnerController extends Controller
{
    /**
     * Display stores based on role.
     * Super Admin sees everything; Owner sees only their own store record.
     */
    public function index(\Illuminate\Http\Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    try {
        // 🛡️ PERSPECTIVE 1: If the logged-in user is a SUPER ADMIN, show ALL owners globally!
        if ($user->role === 'super_admin') {
            $owners = Owner::query()
                ->with(['user', 'subscription'])
                ->latest()
                ->get();

            return response()->json($owners);
        }

        // 💼 PERSPECTIVE 2: If logged in as an individual OWNER, only show their single shop entry
        if ($user->role === 'owner') {
            $owners = Owner::query()
                ->with(['user', 'subscription'])
                ->where('user_id', $user->id) // This is perfect for the owner accounts themselves
                ->get();

            return response()->json($owners);
        }

        return response()->json(['error' => 'Unauthorized role perspective.'], 403);

    } catch (\Exception $e) {
        // Emergency Fallback query line so your table never breaks completely
        return response()->json(Owner::query()->latest()->get());
    }
}

    /**
     * Create a new Store and User profile (Super Admin Only feature)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'nullable|email|unique:users,email',
            'password'         => 'required|string|min:6',
            'phone'            => 'nullable|string|max:20',
            'shop_name'        => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'plan'             => 'in:trial,basic,pro',
        ]);

        // 🌟 FIX: Assign the transaction block return straight to $result!
        $result = DB::transaction(function () use ($data) {
            $email = $data['email'] ?? $data['phone'] . '-' . rand(10, 99) . '@phumyerng.local';

            // Automatic Company Code Generation Logic:
            $cleanShopName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['shop_name']));
            $autoCompanyCode = substr($cleanShopName, 0, 10) . '-' . rand(1000, 9999);

            $user = User::create([
                'name'         => $data['name'],
                'email'        => $email,
                'password'     => Hash::make($data['password']),
                'role'         => 'owner',
                'phone'        => $data['phone'],
                'company_code' => $autoCompanyCode,
            ]);

            $owner = Owner::create([
                'user_id'          => $user->id,
                'shop_name'        => $data['shop_name'],
                'shop_description' => $data['shop_description'] ?? null,
                'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
                'status'           => 'active',
            ]);

            Subscription::create([
                'owner_id'   => $owner->id,
                'plan'       => $data['plan'] ?? 'trial',
                'status'     => 'active',
                'starts_at'  => now(),
                'expires_at' => now()->addMonth(),
            ]);

            return $owner->load(['user', 'subscription']);
        });

        // 🌟 Now $result is fully defined and loaded with all relations!
        return response()->json($result, 201);
    }

    /**
     * Show detailed analytics for a single store profile
     */
    public function show(Owner $owner)
    {
        return response()->json($owner->load('user', 'subscription', 'products', 'orders'));
    }

    /**
     * Generate the direct copyable link for the Telegram Mini-App view
     */
    public function getMyLink(Request $request)
    {
        $user = $request->user();

        // Find the owner profile dynamically
        $owner = Owner::query()->where('user_id', $user->id)->first();
        $ownerId = $owner ? $owner->id : null;
        $botUsername = "phumyerng_bot";

        $link = "https://t.me/{$botUsername}/app?startapp={$ownerId}";

        return response()->json(['link' => $link]);
    }

    /**
     * Update details for a specific store profile
     */
    public function update(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'shop_name'        => 'string|max:255',
            'shop_description' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'status'           => 'in:active,suspended,trial',
        ]);

        $owner->update($data);
        return response()->json($owner->load('user', 'subscription'));
    }

    /**
     * Dynamic KPI Analytics calculation for both Super Admin and Individual Owners
     */
    public function dashboardStats(Request $request)
{
    $user = $request->user();

    if ($user->role === 'super_admin') {
        // Safe check for revenue even if payments table is empty
        $revenue = Schema::hasTable('payments') ? (float) DB::table('payments')->where('status', 'completed')->sum('amount') : 0.0;
        $orders = Schema::hasTable('orders') ? (int) DB::table('orders')->where('created_at', '>=', now()->subDay())->count() : 0;

        return response()->json([
            'total_shops'          => (int) Owner::query()->count(), // This will now show your real database row count!
            'active_subscriptions' => (int) Subscription::query()->where('status', 'active')->count(),
            'total_revenue'        => $revenue,
            'recent_orders'        => $orders,
            'shops_by_status'      => [
                'active'    => (int) Owner::query()->where('status', 'active')->count(),
                'suspended' => (int) Owner::query()->where('status', 'suspended')->count(),
                'trial'     => (int) Owner::query()->where('status', 'trial')->count(),
            ]
        ]);
    }

    // Individual Owner Portal Stats
    $owner = Owner::query()->where('user_id', $user->id)->first();
    if (!$owner) {
        return response()->json([
            'total_shops' => 0, 'active_subscriptions' => 0, 'total_revenue' => 0, 'recent_orders' => 0,
            'shops_by_status' => ['active' => 0, 'suspended' => 0, 'trial' => 0]
        ]);
    }

    $ownerOrders = Schema::hasTable('orders') ? (int) DB::table('orders')->where('owner_id', $owner->id)->where('created_at', '>=', now()->subDay())->count() : 0;

    return response()->json([
        'total_shops'          => 1,
        'active_subscriptions' => (int) Subscription::query()->where('owner_id', $owner->id)->where('status', 'active')->count(),
        'total_revenue'        => Schema::hasTable('payments') ? (float) DB::table('payments')->where('owner_id', $owner->id)->where('status', 'completed')->sum('amount') : 0.0,
        'recent_orders'        => $ownerOrders,
        'shops_by_status'      => [
            'active'    => $owner->status === 'active' ? 1 : 0,
            'suspended' => $owner->status === 'suspended' ? 1 : 0,
            'trial'     => $owner->status === 'trial' ? 1 : 0,
        ]
    ]);
}

    /**
     * Alter subscription statuses on-the-fly
     */
    public function toggleStatus(Request $request, Owner $owner)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,trial'
        ]);

        $owner->update(['status' => $request->status]);

        return response()->json([
            'message' => "Shop status updated to {$request->status}",
            'owner' => $owner
        ]);
    }

    /**
     * Structural Transaction Cascade Delete Flow
     */
    public function destroy(\App\Models\Owner $owner)
    {
        try {
            return DB::transaction(function () use ($owner) {
                if ($owner->subscription) {
                    $owner->subscription()->delete();
                }

                if ($owner->products()->exists()) {
                    $owner->products()->delete();
                }

                if ($owner->orders()->exists()) {
                    $owner->orders()->delete();
                }

                if ($owner->user) {
                    $owner->user()->delete();
                } else {
                     $owner->query()->find($owner->id)?->delete();
                }

                return response()->json([
                    'message' => 'Owner and all linked data deleted successfully.'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to safely clean data constraints.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend or modify subscription data logs
     */
    public function updateSubscription(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'plan'       => 'required|in:trial,basic,pro',
            'expires_at' => 'required|date',
        ]);

        $sub = $owner->subscription ?? new Subscription(['owner_id' => $owner->id]);
        $sub->fill([
            'plan'       => $data['plan'],
            'status'     => 'active',
            'starts_at'  => now(),
            'expires_at' => $data['expires_at'],
        ])->save();

        return response()->json($sub);
    }
}
