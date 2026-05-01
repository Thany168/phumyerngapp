<?php

namespace App\Http\Controllers\Api; // or your specific folder

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // For the Telegram API call
use Illuminate\Support\Facades\Log;  // THIS FIXES THE LOG ERROR

class CheckoutController extends Controller
{
    public function store(Request $request)
{
    $validated = $request->validate([
        'owner_id' => 'required|exists:owners,id',
        'items' => 'required|array',
        'total_amount' => 'required|numeric',
        'customer_name' => 'required|string',
        'phone' => 'nullable|string',
        'location' => 'nullable|string',
    ]);

    $order = Order::create([
    'owner_id'      => $validated['owner_id'],
    'user_id'       => auth()->id,
    'customer_name' => $validated['customer_name'],
    'phone'         => $validated['phone'],     // Match your DB column name
    'location'      => $validated['location'],  // Match your DB column name
    'total_amount'  => $validated['total_amount'],
    'status'        => 'pending',
]);

    foreach ($validated['items'] as $item) {
        $order->items()->create([
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
        ]);
    }

    $owner = Owner::find($validated['owner_id']);

    if ($owner && $owner->telegram_chat_id) {
        Log::info("Notifying Owner: " . $owner->shop_name);
        $this->notifyOwnerViaTelegram($owner, $order);
    }

    return response()->json(['message' => 'Order Success!', 'id' => $order->id], 201);
}

    private function notifyOwnerViaTelegram($owner, $order)
{
    $token = env('TELEGRAM_BOT_TOKEN');

    // Using string concatenation (.) is safer and avoids the PHP 8.2 interpolation errors
    $message = "🚀 *New Order Alert!*\n\n" .
               "🏪 *Shop:* " . $owner->shop_name . "\n" .
               "👤 *Customer:* " . $order->customer_name . "\n" .
               "💰 *Total:* $" . number_format($order->total_amount, 2) . "\n" .
               "📞 *Contact:* " . ($order->phone ?? 'N/A') . "\n\n" .
               "👉 _Check your dashboard to confirm this order._";

    try {
    $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
        'chat_id' => $owner->telegram_chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ]);

    if (!$response->successful()) {
        // This will log exactly why Telegram rejected the message
        Log::error("Telegram API Error: " . $response->body());
    }
} catch (\Exception $e) {
    Log::error("Connection Error: " . $e->getMessage());
}
}
}
