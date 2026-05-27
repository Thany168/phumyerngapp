<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function store(Request $request, $owner)
    {
        $request->validate([
            'phone'              => 'required|string|max:30',
            'location'           => 'required|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // 🎯 THE FIX: Extract the integer ID dynamically whether the route passes an object or a raw ID!
        $resolvedOwnerId = is_object($owner) ? $owner->id : (int)$owner;

        \Log::info("🛒 Processing guest checkout sequence for explicit Owner ID: " . $resolvedOwnerId);

        $user = $request->user();

        // Forward everything safely down to your service container layer
        $order = $this->orderService->createOrder(
            [
                'user_id'     => $user?->id ?? null,
                'telegram_id' => $request->input('telegram_id') ?? $user?->telegram_id ?? '',
                'name'        => $request->input('name') ?? $user?->name ?? 'Guest Customer',
                'phone'       => $request->phone,
                'location'    => $request->location,
            ],
            $request['items'],
            $resolvedOwnerId // 🚀 Force the specific store ID (e.g., 33) down to the query state
        );

        return response()->json($order, 201);
    }
}
