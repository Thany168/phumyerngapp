<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    public function destroy(Owner $owner)
    {
        $owner->user->delete(); // cascades to owner
        return response()->json(['message' => 'Owner deleted']);
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
