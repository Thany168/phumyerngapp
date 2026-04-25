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

    public function index(Request $request)
    {
        $query = Order::where('owner_id', $request->user()->owner->id)
            ->with('items.product', 'payment', 'delivery.deliveryUser')
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

   public function store(Request $request, \App\Models\Owner $owner)
{
    // Match the React keys exactly
    $validated = $request->validate([
        'items' => 'required|array',
        'total_amount' => 'required|numeric', // Changed from total_price to total_amount
        'phone' => 'nullable|string',
        'location' => 'nullable|string',
    ]);

    // 1. Create the Order linked to the Owner from the URL
    $order = Order::create([
        'user_id' => auth()->id ?? null,// This prevents the error if not logged in
        'owner_id' => $owner->id, // Get from URL parameter automatically
        'total_amount' => $validated['total_amount'],
        'phone' => $validated['phone'] ?? null,
        'location' => $validated['location'] ?? null,
        'status' => 'pending',
    ]);

    // 2. Create Order Items
    foreach ($validated['items'] as $item) {
        $order->items()->create([
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
        ]);
    }

    // 3. Return the order WITH the ID for the next step (Payment Upload)
    return response()->json($order, 201);
}

    public function show(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json($order->load('items.product', 'payment', 'delivery.deliveryUser'));
    }

    public function confirm(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json(
            $this->orderService->confirmOrder($order, $request->user()->id)
        );
    }

    public function reject(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['reason' => 'nullable|string|max:500']);
        return response()->json(
            $this->orderService->rejectOrder($order, $request->input('reason', ''))
        );
    }

    public function assignDelivery(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['delivery_user_id' => 'required|integer|exists:users,id']);
        return response()->json(
            $this->orderService->assignDelivery($order, $request->delivery_user_id)
        );
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
