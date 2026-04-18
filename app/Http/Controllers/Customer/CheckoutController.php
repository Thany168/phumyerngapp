<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OrderService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function store(Request $request, Owner $owner)
    {
        $data = $request->validate([
            'phone'              => 'required|string|max:30',
            'location'           => 'required|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $user  = $request->user();
        $order = $this->orderService->createOrder(
            [
                'user_id'     => $user->id,
                'telegram_id' => $user->telegram_id ?? '',
                'name'        => $user->name,
                'phone'       => $data['phone'],
                'location'    => $data['location'],
            ],
            $data['items'],
            $owner->id
        );

        return response()->json($order, 201);
    }
}
