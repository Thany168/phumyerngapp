<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        // Ensure user is logged in
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product', 'payment', 'delivery'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        // Safety check: Does this order belong to the logged-in user?
        if (!$request->user() || $order->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        return response()->json($order->load(['items.product', 'payment', 'delivery']));
    }

    public function uploadPayment(Request $request, Order $order)
    {
        // Check ownership
        if (!$request->user() || $order->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'screenshot' => 'required|image|max:5120' // 5MB Max
        ]);

        // Store the file
        $path = $request->file('screenshot')->store('payments', 'public');

        // Save to the payment relationship
        $order->payment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'screenshot_path' => $path,
                'screenshot_url'  => asset('storage/' . $path),
                'status'          => 'pending',
            ]
        );

        return response()->json(['message' => 'Payment screenshot uploaded successfully']);
    }
}
