<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Delivery;

class DeliveryService
{
    public function __construct(private TelegramNotificationService $telegram) {}

    public function notifyDeliveryStaff(Delivery $delivery): void
    {
        $staffTelegramId = $delivery->deliveryUser->telegram_id ?? null;
        if (!$staffTelegramId) return;

        $order = $delivery->order;
        $items = $order->items->map(fn($i) => "• {$i->product_name} x{$i->quantity}")->join("\n");

        $text = "<b>New Delivery Task</b>\n\n"
            . "Order #{$order->id}\n"
            . "Customer: {$order->customer_name}\n"
            . "Phone: {$order->customer_phone}\n"
            . "Location: {$order->delivery_location}\n\n"
            . "Items:\n{$items}";

        $markup = ['inline_keyboard' => [[
            ['text' => 'Mark as Delivered', 'callback_data' => "delivered:{$order->id}"],
        ]]];

        $this->telegram->sendMessage($staffTelegramId, $text, $markup);
    }

    public function markDelivered(Delivery $delivery): Delivery
    {
        $delivery->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        $order = $delivery->order;
        $order->update([
            'status'       => OrderStatus::Delivered,
            'delivered_at' => now(),
        ]);

        if ($order->customer_telegram_id) {
            $this->telegram->notifyCustomerOrderStatus(
                $order->customer_telegram_id,
                $order->fresh()
            );
        }

        return $delivery->fresh();
    }
}
