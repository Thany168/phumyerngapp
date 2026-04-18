<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product', 'payment', 'delivery')
            ->orderByDesc('created_at')
            ->get();
        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) abort(403);
        return response()->json($order->load('items.product', 'payment', 'delivery'));
    }

    public function uploadPayment(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) abort(403);
        $request->validate(['screenshot' => 'required|image|max:5120']);

        $path = $request->file('screenshot')->store('payments', 'public');

        $order->payment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'screenshot_path' => $path,
                'screenshot_url'  => asset('storage/' . $path),
                'status'          => 'pending',
            ]
        );

        return response()->json(['message' => 'Payment screenshot uploaded']);
    }
}
