<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OrderService;
use Illuminate\Http\Request;
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

        $user  = $request->user();
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

        $this->notifyOwner($order);

        return response()->json($order, 201);
    }
    private function notifyOwner($order)
        {
            $owner = $order->owner; // Make sure your relationships are set
            $botToken = config('telegram.bot_token');

            if (!$botToken) {
                Log::info('Telegram bot token not set, skipping notification');
                return;
            }

            $text = "🔔 *New Order #{$order->id}*\n";
            $text .= "👤 Customer: {$order->customer_name}\n";
            $text .= "📞 Phone: {$order->customer_phone}\n";
            $text .= "📍 Location: {$order->delivery_location}\n";

            try {
                \Illuminate\Support\Facades\Http::withOptions(['verify' => false])->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $owner->telegram_chat_id,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '✅ Confirm', 'callback_data' => "confirm_order_{$order->id}"],
                            ['text' => '❌ Reject', 'callback_data' => "reject_order_{$order->id}"]
                        ]]
                    ]
                ]);
            } catch (\Exception $e) {
                Log::error('Telegram sendMessage failed: ' . $e->getMessage());
            }
        }
}
