<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // List all orders belonging to this owner
    public function index(Request $request)
    {
        $ownerId = $request->user()->owner->id;

        $orders = Order::where('owner_id', $ownerId)
            ->with('items.product', 'payment', 'delivery.deliveryUser')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($orders);
    }

    // Get the unique shop link for this owner
    public function getMyLink(Request $request)
    {
        if (!$request->user()->owner) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $ownerId = $request->user()->owner->id;
        $botUsername = "phumyerng_bot";

        $link = "https://t.me/{$botUsername}/app?startapp={$ownerId}";

        return response()->json([
            'owner_id' => $ownerId,
            'link' => $link,
            'qr_data' => $link
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json($order->load('items.product', 'payment', 'delivery.deliveryUser'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);

        $request->validate(['status' => 'required|in:pending,confirmed,completed,rejected']);
        $order->update(['status' => $request->status]);

        return response()->json(['message' => 'Order status updated to ' . $request->status]);
    }

    public function confirm(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json($this->orderService->confirmOrder($order, $request->user()->id));
    }

    public function reject(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['reason' => 'nullable|string|max:500']);
        return response()->json($this->orderService->rejectOrder($order, $request->input('reason', '')));
    }

    public function assignDelivery(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['delivery_user_id' => 'required|integer|exists:users,id']);
        return response()->json($this->orderService->assignDelivery($order, $request->delivery_user_id));
    }

    public function deliveryStaff(Request $request)
    {
        $staff = User::where('role', 'delivery')->get(['id', 'name', 'telegram_username']);
        return response()->json($staff);
    }

    private function checkOwner(Request $request, Order $order): void
    {
        if ($order->owner_id !== $request->user()->owner->id) abort(403);
    }
}
