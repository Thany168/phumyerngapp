<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private OrderService    $orderService,
        private DeliveryService $deliveryService,
    ) {}

    public function handle(Request $request)
    {
        $update = $request->all();
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
        return response()->json(['ok' => true]);
    }

    private function handleCallback(array $callback): void
    {
        $data    = $callback['data'] ?? '';
        $parts   = explode(':', $data);
        $action  = $parts[0] ?? '';
        $orderId = (int) ($parts[1] ?? 0);

        if (!$orderId) return;

        $order = Order::with('items.product', 'payment', 'owner', 'delivery')->find($orderId);
        if (!$order) return;

        match ($action) {
            'confirm_order' => $this->orderService->confirmOrder($order, 0),
            'reject_order'  => $this->orderService->rejectOrder($order, 'Rejected by owner'),
            'delivered'     => $order->delivery
                ? $this->deliveryService->markDelivered($order->delivery)
                : null,
            default         => null,
        };
    }
}
