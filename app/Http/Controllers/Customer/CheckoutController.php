<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Corrected this line
use Illuminate\Support\Facades\Log;

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

        $user = $request->user();
        $order = $this->orderService->createOrder(
            [
                'user_id'     => $user->id,
                'telegram_id' => $user->telegram_id ?? '',
                'name'        => $user->name,
                'phone'       => $request->phone,
                'location'    => $request->location,
            ],
            $request['items'],
            $owner->id
        );

        // Fixed the argument order: $owner goes first, then $order
       $this->notifyOwnerViaTelegram($owner, $order);

        return response()->json($order, 201);
    }

    private function notifyOwnerViaTelegram($owner, $order)
    {
        $token = $owner->telegram_bot_token;
        $groupId = $owner->telegram_chat_id; // Holds the group ID starting with "-"

        // Stop if the owner hasn't configured their group/bot yet
        if (!$token || !$groupId) {
            Log::warning("Skipping notification for Owner ID {$owner->id}: Missing token or group ID.");
            return;
        }

        $message = "🔔 *NEW ORDER PLACED!*\n\n" .
                   "🏪 *Shop:* " . $owner->shop_name . "\n" .
                   "👤 *Customer:* " . $order->customer_name . "\n" .
                   "💰 *Total:* $" . number_format($order->total_amount, 2) . "\n" .
                   "📞 *Contact:* " . ($order->customer_phone ?? 'N/A') . "\n\n" .
                   "👉 _Please review the dashboard to process this order._";

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $groupId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to notify group for Owner {$owner->id}: " . $e->getMessage());
        }
    }
}
