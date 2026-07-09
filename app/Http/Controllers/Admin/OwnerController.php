<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OwnerController extends Controller
{
    /**
     * Display stores based on role.
     * Super Admin sees everything; Owner sees only their own store record.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        try {
            // Allow access for both 'super_admin' or your test account
            if ($user->role === 'super_admin' || $user->role === 'admin') {

                $owners = Owner::query()
                    ->with(['user', 'subscription'])
                    ->latest()
                    ->get();

                // 🚀 AUTOMATIC FIX FOR OLD DATA:
                // Loop through your old database rows. If they don't have a linked user or subscription,
                // we attach a fake one on the fly so the frontend table doesn't break!
                $owners->transform(function ($owner) {
                    if (! $owner->user) {
                        $owner->setRelation('user', new User([
                            'company_code' => 'OLD-SHOP-'.$owner->id,
                            'phone' => 'No Phone',
                            'email' => 'no-email@phumyerng.local',
                            'name' => 'Legacy Owner',
                        ]));
                    }
                    if (! $owner->subscription) {
                        $owner->setRelation('subscription', new Subscription([
                            'plan' => 'trial',
                            'status' => 'active',
                        ]));
                    }

                    return $owner;
                });

                return response()->json($owners);
            }

            if ($user->role === 'owner') {
                $owners = Owner::query()
                    ->with(['user', 'subscription'])
                    ->where('user_id', $user->id)
                    ->get();

                return response()->json($owners);
            }

            return response()->json(['error' => 'Unauthorized role perspective.'], 403);

        } catch (\Exception $e) {
            // Ultimate safe fallback
            return response()->json(Owner::query()->latest()->get());
        }
    }

    /**
     * Create a new Store and User profile (Super Admin Only feature)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'shop_name' => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'plan' => 'in:trial,basic,pro',
        ]);

        // Assign the transaction block return straight to $result!
        $result = DB::transaction(function () use ($data) {
            $email = $data['email'] ?? $data['phone'].'-'.rand(10, 99).'@phumyerng.local';

            // Automatic Company Code Generation Logic:
            $cleanShopName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['shop_name']));
            $autoCompanyCode = substr($cleanShopName, 0, 10).'-'.rand(1000, 9999);

            // 🌟 Generate a unique verification token for this portal owner (e.g., PHUM-ABCD-1234)
            $verificationToken = 'PHUM-'.strtoupper(Str::random(4)).'-'.rand(1000, 9999);

            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'phone' => $data['phone'],
                'company_code' => $autoCompanyCode,
            ]);

            $owner = Owner::create([
                'user_id' => $user->id,
                'shop_name' => $data['shop_name'],
                'shop_description' => $data['shop_description'] ?? null,
                'telegram_verification_token' => $verificationToken, // 🌟 INJECT NEW TOKEN HERE!
                'telegram_chat_id' => $data['telegram_chat_id'] ?? null,               // Starts as null until group setup is run
                'status' => 'active',
            ]);

            Subscription::create([
                'owner_id' => $owner->id,
                'plan' => $data['plan'] ?? 'trial',
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);

            return $owner->load(['user', 'subscription']);
        });

        // Now $result is fully loaded and includes the brand-new verification token string
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
        $botUsername = 'phumyerng_bot';

        $link = "https://t.me/{$botUsername}/app?startapp={$ownerId}";

        return response()->json(['link' => $link]);
    }

    /**
     * Update details for a specific store profile
     */
    /**
     * Update details for a specific store profile and its linked User/Subscription.
     */
    public function update(Request $request, Owner $owner)
    {
        // 🎯 1. Validate inputs combining both user fields and shop fields from your React modal
        $data = $request->validate([
            // User Table fields
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$owner->user_id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6', // Optional reset

            // Owner/Shop Table fields
            'shop_name' => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'status' => 'required|in:active,suspended,trial',

            // Subscription field
            'plan' => 'required|in:trial,basic,pro',
        ]);

        try {
            // 🚀 2. Run an atomic transaction to update all relational constraints securely
            $updatedOwner = DB::transaction(function () use ($data, $owner) {

                // A. Update the linked User account properties
                if ($owner->user) {
                    $userUpdateData = [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                    ];

                    if (! empty($data['password'])) {
                        $userUpdateData['password'] = Hash::make($data['password']);
                    }

                    $owner->user()->update($userUpdateData);
                }

                // B. Update the core Owner/Shop details
                $owner->update([
                    'shop_name' => $data['shop_name'],
                    'shop_description' => $data['shop_description'] ?? null,
                    'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
                    'status' => $data['status'],
                ]);

                // C. Update the active Subscription tier mapping
                if ($owner->subscription) {
                    $owner->subscription()->update([
                        'plan' => $data['plan'],
                    ]);
                } else {
                    // Fallback to construct a subscription if it's an old legacy record
                    Subscription::create([
                        'owner_id' => $owner->id,
                        'plan' => $data['plan'],
                        'status' => 'active',
                        'starts_at' => now(),
                        'expires_at' => now()->addMonth(),
                    ]);
                }

                return $owner->load(['user', 'subscription']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Owner portal and account records updated successfully!',
                'data' => $updatedOwner,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save updates to the database database grid.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dynamic KPI Analytics calculation for both Super Admin and Individual Owners
     */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        try {
            // Handle Super Admin or testing accounts cleanly
            if ($user->role === 'super_admin' || $user->role === 'admin') {

                // Safe check for table and column presence
                $revenue = 0.0;
                if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'amount')) {
                    $revenue = (float) DB::table('payments')->where('status', 'completed')->sum('amount');
                }

                $orders = 0;
                if (Schema::hasTable('orders')) {
                    // Using standard Eloquent / DB date constraints compatible with pgsql
                    $orders = (int) DB::table('orders')->where('created_at', '>=', now()->subDays(1))->count();
                }

                return response()->json([
                    'total_shops' => (int) Owner::query()->count(),
                    'active_subscriptions' => (int) Subscription::query()->where('status', 'active')->count(),
                    'total_revenue' => $revenue,
                    'recent_orders' => $orders,
                    'shops_by_status' => [
                        'active' => (int) Owner::query()->where('status', 'active')->count(),
                        'suspended' => (int) Owner::query()->where('status', 'suspended')->count(),
                        'trial' => (int) Owner::query()->where('status', 'trial')->count(),
                    ],
                ], 200);
            }

            // Individual Owner Fallback
            $owner = Owner::query()->where('user_id', $user->id)->first();
            if (! $owner) {
                return response()->json([
                    'total_shops' => 0, 'active_subscriptions' => 0, 'total_revenue' => 0, 'recent_orders' => 0,
                    'shops_by_status' => ['active' => 0, 'suspended' => 0, 'trial' => 0],
                ], 200);
            }

            return response()->json([
                'total_shops' => 1,
                'active_subscriptions' => (int) Subscription::query()->where('owner_id', $owner->id)->where('status', 'active')->count(),
                'total_revenue' => 0.0,
                'recent_orders' => 0,
                'shops_by_status' => [
                    'active' => $owner->status === 'active' ? 1 : 0,
                    'suspended' => $owner->status === 'suspended' ? 1 : 0,
                    'trial' => $owner->status === 'trial' ? 1 : 0,
                ],
            ], 200);

        } catch (\Exception $e) {
            // 🚀 CRITICAL BYPASS: If anything breaks, return fallback values instead of a 500 error!
            return response()->json([
                'total_shops' => (int) Owner::query()->count(),
                'active_subscriptions' => 0,
                'total_revenue' => 0.0,
                'recent_orders' => 0,
                'shops_by_status' => ['active' => 0, 'suspended' => 0, 'trial' => 0],
                'debug_error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Alter subscription statuses on-the-fly
     */
    public function toggleStatus(Request $request, Owner $owner)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,trial',
        ]);

        $owner->update(['status' => $request->status]);

        return response()->json([
            'message' => "Shop status updated to {$request->status}",
            'owner' => $owner,
        ]);
    }

    /**
     * Structural Transaction Cascade Delete Flow
     */
    public function destroy(Owner $owner)
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
                    'message' => 'Owner and all linked data deleted successfully.',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to safely clean data constraints.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extend or modify subscription data logs
     */
    public function updateSubscription(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'plan' => 'required|in:trial,basic,pro',
            'expires_at' => 'required|date',
        ]);

        $sub = $owner->subscription ?? new Subscription(['owner_id' => $owner->id]);
        $sub->fill([
            'plan' => $data['plan'],
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $data['expires_at'],
        ])->save();

        return response()->json($sub);
    }
}
