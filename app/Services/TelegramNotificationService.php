<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    private string $baseUrl;

    public function __construct()
    {
        $token = config('telegram.bot_token');
        $this->baseUrl = config('telegram.api_url') . $token;
    }

    public function sendMessage(string $chatId, string $text, array $replyMarkup = []): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if (!empty($replyMarkup)) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        try {
            Http::post("{$this->baseUrl}/sendMessage", $payload);
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage failed: ' . $e->getMessage());
        }
    }

    public function notifyOwnerNewOrder(string $ownerChatId, $order): void
    {
        $items = $order->items->map(fn($i) => "• {$i->product_name} x{$i->quantity}")->join("\n");
        $text  = "🛒 <b>New Order #{$order->id}</b>\n\n"
            . "Customer: {$order->customer_name}\n"
            . "Phone: {$order->customer_phone}\n"
            . "Location: {$order->delivery_location}\n\n"
            . "Items:\n{$items}\n\n"
            . "Total: $" . number_format($order->total_amount, 2);

        $markup = ['inline_keyboard' => [[
            ['text' => 'Confirm Payment',  'callback_data' => "confirm_order:{$order->id}"],
            ['text' => 'Reject',           'callback_data' => "reject_order:{$order->id}"],
        ]]];

        $this->sendMessage($ownerChatId, $text, $markup);
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
