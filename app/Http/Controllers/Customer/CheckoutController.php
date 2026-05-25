<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // 🚀 Fix: Drop 'Owner' type-hinting to prevent implicit database lookups
public function store(Request $request, $ownerId)
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
        $ownerId // ⚡ Pass the raw ID integer straight through to the service class
    );

    // Fire off the real-time notification alert stream
    $this->notifyOwner($order);

    return response()->json($order, 201);
}

    private function notifyOwner($order)
    {
        $owner = $order->owner ?? Owner::query()->find($order->owner_id);

        if (!$owner || !$owner->telegram_chat_id) {
            Log::warning("Skipping notification: Owner or chat ID missing.");
            return;
        }

        // Always use your single central system token configuration
        $botToken = config('telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');

        $text = "🔔 *NEW ORDER RECEIVED #{$order->id}*\n\n";
        $text .= "👤 Customer: " . ($order->customer_name ?? $order->name ?? 'Guest') . "\n";
        $text .= "📞 Phone: " . ($order->customer_phone ?? $order->phone) . "\n";
        $text .= "📍 Location: " . ($order->delivery_location ?? $order->location) . "\n\n";
        $text .= "⚡ Please process this order via the interactive buttons below.";

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '✅ Confirm', 'callback_data' => "confirm_order_{$order->id}"],
                ['text' => '❌ Reject', 'callback_data' => "reject_order_{$order->id}"]
            ]]
        ];

        try {
            Http::withoutVerifying()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id'      => (string)$owner->telegram_chat_id, // Routes to that owner's specific group!
                    'text'         => $text,
                    'parse_mode'   => 'Markdown',
                    'reply_markup' => json_encode($keyboard)
                ]);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage crashed: ' . $e->getMessage());
        }
    }
}
