<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private DeliveryService $deliveryService) {}

    public function index(Request $request)
    {
        $tasks = Delivery::where('delivery_user_id', $request->user()->id)
            ->with('order.items.product', 'order.owner')
            ->orderByDesc('created_at')
            ->get();
        return response()->json($tasks);
    }

    public function markDelivered(Request $request, Delivery $delivery)
    {
        if ($delivery->delivery_user_id !== $request->user()->id) abort(403);
        return response()->json(
            $this->deliveryService->markDelivered($delivery)
        );
    }
}
