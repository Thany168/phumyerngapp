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
        $query = Order::query()->where('owner_id', $this->ownerId($request))
            ->with('items.product', 'payment', 'delivery.deliveryUser')
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }
    // app/Http/Controllers/Owner/OrderController.php

    public function getMyLink(Request $request)
    {
        $ownerId = $this->ownerId($request);
        $botUsername = "phumyerng_bot"; // or env('TELEGRAM_BOT_USERNAME')

        $link = "https://t.me/{$botUsername}?startapp={$ownerId}";

        return response()->json([
            'owner_id' => $ownerId,
            'link' => $link,
            'qr_data' => $link // You can use this string to generate a QR code in React
        ]);
    }

  public function store(Request $request, $ownerId)
    {
        // 🎯 1. Match the React JSON keys exactly with strict validation parameters
        $validated = $request->validate([
            'items'        => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.price'      => 'required|numeric',
            'total_amount' => 'required|numeric',
            'phone'        => 'nullable|string',
            'location'     => 'nullable|string',
        ]);

        // 🎯 2. Safely create the root Order transaction instance
        $order = Order::create([
            'user_id'      => auth()->id(), // Captures authenticated customer profile identifier safely if logged in
            'owner_id'     => $ownerId,     // Directly reads the raw target store numerical parameter from the URL string tracker!
            'total_amount' => $validated['total_amount'],
            'phone'        => $validated['phone'] ?? null,
            'location'     => $validated['location'] ?? null,
            'status'       => 'pending',
        ]);

        // 🎯 3. Loop through and attach Order Items tracking matrices
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
            ]);
        }

        // 🎯 4. Load relationships for frontend responses uniformity mapping
        return response()->json($order->load('items.product'), 201);
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
        $staff = User::query()->where('role', 'delivery')->get(['id', 'name', 'telegram_username']);
        return response()->json($staff);
    }

    private function checkOwner(Request $request, Order $order): void
    {
        if ($order->owner_id !== $this->ownerId($request)) abort(403);
    }

}
