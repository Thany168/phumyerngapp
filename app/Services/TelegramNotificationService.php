<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    private string $baseUrl;

    public function __construct()
    {
        // 🚀 Fetch with robust env() file backups in case config arrays are cached out
        $token  = config('telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $apiUrl = config('telegram.api_url') ?? env('TELEGRAM_API_URL', 'https://api.telegram.org/bot');

        // 🎯 Ensure the base string terminates cleanly with 'bot' followed directly by the token
        $cleanApiUrl = rtrim($apiUrl, '/');

        if (!str_ends_with($cleanApiUrl, 'bot') && !str_contains($cleanApiUrl, 'bot' . $token)) {
            $cleanApiUrl .= '/bot';
        }

        $this->baseUrl = $cleanApiUrl . $token;
    }

   public function sendMessage(string $chatId, string $text, array $replyMarkup = []): void
    {
        // 🎯 FIX: Completely removed the forced '-100' supergroup prefix addition!
        // This allows standard group IDs like -5208478672 to work perfectly.

        $payload = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ];

        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        \Log::info("🤖 Pushing request to Telegram: " . "{$this->baseUrl}/sendMessage");
        \Log::info("📦 Target Destination Chat ID: " . $chatId);

        try {
            $response = Http::withoutVerifying()->post("{$this->baseUrl}/sendMessage", $payload);

            if (!$response->successful()) {
                \Log::error("❌ Telegram Server Explicit Rejection: " . $response->body());
            } else {
                \Log::info("✅ Telegram alert successfully delivered to chat group!");
            }
        } catch (\Exception $e) {
            \Log::error('Telegram Transport Exception Crash: ' . $e->getMessage());
        }
    }

    public function notifyOwnerNewOrder(string $ownerChatId, $order): void
    {
        $items = $order->items->map(fn($i) => "• {$i->product_name} x{$i->quantity}")->join("\n");

        // Build optional fields — only show lines if values exist
        $phoneLine    = !empty($order->customer_phone)    ? "\nPhone: {$order->customer_phone}"         : '';
        $locationLine = !empty($order->delivery_location) ? "\nLocation: {$order->delivery_location}"   : '';

        $text  = "🛒 <b>New Order #{$order->id}</b>\n\n"
            . "Customer: " . ($order->customer_name ?? 'Telegram Customer') . $phoneLine . $locationLine . "\n\n"
            . "<b>Items:</b>\n{$items}\n\n"
            . "<b>Total Bill:</b> $" . number_format($order->total_amount, 2);

        $this->sendMessage($ownerChatId, $text);
    }

    public function notifyCustomerOrderStatus(string $chatId, $order): void
    {
        $messages = [
            'confirmed'  => "Your order <b>#{$order->id}</b> has been confirmed! We are preparing your items.",
            'rejected'   => "Sorry, order <b>#{$order->id}</b> was rejected. Please contact the shop.",
            'delivering' => "Your order <b>#{$order->id}</b> is on the way!",
            'delivered'  => "Order <b>#{$order->id}</b> delivered! Thank you.",
        ];

        $status = is_string($order->status) ? $order->status : $order->status->value;
        $text   = $messages[$status] ?? "Order #{$order->id} updated: {$status}";
        $this->sendMessage($chatId, $text);
    }
}
