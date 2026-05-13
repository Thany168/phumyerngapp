<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class OwnerController extends Controller
{
    public function index()
    {
        $owners = Owner::with('user', 'subscription')->paginate(20);
        return response()->json($owners);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:6',
            'shop_name'        => 'required|string|max:255',
            'shop_description' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'plan'             => 'in:trial,basic,pro',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'owner',
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

        return response()->json($owner->load('user', 'subscription'), 201);
    }

    public function show(Owner $owner)
    {
        return response()->json($owner->load('user', 'subscription', 'products', 'orders'));
    }

    public function getMyLink(Request $request) {
    $ownerId = $request->user()->owner->id;
    $botUsername = "phumyerng_bot";

    // This is the link the owner can copy and give to customers
    $link = "https://t.me/{$botUsername}/app?startapp={$ownerId}";

        return response()->json(['link' => $link]);
    }

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
    public function dashboardStats()
{
    /** @var \Illuminate\Database\Eloquent\Builder $ownerQuery */
    $ownerQuery = Owner::query();

    return response()->json([
        'total_shops'          => (int) $ownerQuery->count(),
        'active_subscriptions' => (int) Subscription::query()->where('status', 'active')->count(),
        'total_revenue'        => (float) DB::table('payments')->where('status', 'completed')->sum('amount'),
        'recent_orders'        => (int) DB::table('orders')->where('created_at', '>=', now()->subDay())->count(),
        'shops_by_status'      => [
            'active'    => (int) Owner::query()->where('status', 'active')->count(),
            'suspended' => (int) Owner::query()->where('status', 'suspended')->count(),
            'trial'     => (int) Owner::query()->where('status', 'trial')->count(),
        ]
    ]);
}
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

   public function destroy(\App\Models\Owner $owner) // Explicitly type hint the namespace
{
    try {
        return DB::transaction(function () use ($owner) {
            // ... (keep your existing cleanup code)

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
        // ... (keep your error handling)
    }
}

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
