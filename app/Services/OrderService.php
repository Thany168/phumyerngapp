<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class OrderService
{
    public function __construct(
        private TelegramNotificationService $telegram,
        private DeliveryService             $deliveryService,
    ) {}

   public function createOrder(array $data, array $cartItems, int $ownerId): Order
    {
        // 🚀 Step 1: Core Database Insertion Only
        $order = DB::transaction(function () use ($data, $cartItems, $ownerId) {
            $total         = 0;
            $resolvedItems = [];

            foreach ($cartItems as $item) {
                $pId = $item['product_id'] ?? $item['id'] ?? null;

                $product = Product::query()
                    ->where('owner_id', $ownerId)
                    ->where('id', $pId)
                    ->first();

                if (!$product) {
                    return abort(422, "Product ID {$pId} not found.");
                }

                $subtotal        = $product->price * $item['quantity'];
                $total          += $subtotal;
                $resolvedItems[] = compact('product', 'subtotal') + ['quantity' => $item['quantity']];
            }

            // Create Order safely (Handles null user_id via your Postgres fix)
            $order = Order::create([
                'owner_id'             => $ownerId,
                'user_id'              => $data['user_id'] ?? null,
                'customer_telegram_id' => $data['telegram_id'] ?? null,
                'customer_name'        => $data['name'] ?? 'Telegram Customer',
                'customer_phone'       => $data['phone'] ?? null,       // Optional field
                'delivery_location'    => $data['location'] ?? null,    // Optional field
                'status'               => OrderStatus::Pending,
                'total_amount'         => $total,
            ]);

            // Create Items
            foreach ($resolvedItems as $ri) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $ri['product']->id,
                    'product_name' => $ri['product']->name,
                    'unit_price'   => $ri['product']->price,
                    'quantity'     => $ri['quantity'],
                    'subtotal'     => $ri['subtotal'],
                ]);
            }

            return $order;
        });

        // 🚀 Step 2: Safe Telegram Bot Group Alert Notification (Outside DB Transaction)
        try {
            // Force fetch data directly from the owners table columns mapping
            $ownerRow = \Illuminate\Support\Facades\DB::table('owners')
                ->where('id', $ownerId)
                ->first();

            if ($ownerRow && !empty($ownerRow->telegram_chat_id)) {

                // 📝 Log tracing check before sending
                \Log::info("🤖 OrderService triggering bot alert to chat: " . $ownerRow->telegram_chat_id);

                $this->telegram->notifyOwnerNewOrder(
                    trim($ownerRow->telegram_chat_id),
                    $order
                );

            } else {
                \Log::warning("⚠️ Telegram alert skipped: Owner ID {$ownerId} has no telegram_chat_id inside pgAdmin owners table row cell.");
            }
        } catch (\Exception $telegramEx) {
            // This captures the exact line crash detail inside your storage/logs/laravel.log file!
            \Log::error('❌ Telegram Bot Dispatch Layer Crashed: ' . $telegramEx->getMessage());
        }

        return $order->load('items.product');
    }

    public function confirmOrder(Order $order, int $verifiedBy): Order
    {
        return DB::transaction(function () use ($order, $verifiedBy) {
            $order->update([
                'status'       => OrderStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            if ($order->payment) {
                $order->payment->update([
                    'status'      => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $verifiedBy ?: null,
                ]);
            }

            if ($order->customer_telegram_id) {
                $this->telegram->notifyCustomerOrderStatus(
                    $order->customer_telegram_id,
                    $order->fresh()
                );
            }

            return $order->fresh();
        });
    }

    public function rejectOrder(Order $order, string $reason = ''): Order
    {
        $order->update(['status' => OrderStatus::Rejected]);

        if ($order->payment) {
            $order->payment->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
            ]);
        }

        if ($order->customer_telegram_id) {
            $this->telegram->notifyCustomerOrderStatus(
                $order->customer_telegram_id,
                $order->fresh()
            );
        }

        return $order->fresh();
    }

    public function assignDelivery(Order $order, int $deliveryUserId): Order
    {
        return DB::transaction(function () use ($order, $deliveryUserId) {
            $order->update(['status' => OrderStatus::Delivering]);

            $delivery = \App\Models\Delivery::create([
                'order_id'         => $order->id,
                'owner_id'         => $order->owner_id,
                'delivery_user_id' => $deliveryUserId,
                'status'           => 'assigned',
                'assigned_at'      => now(),
            ]);

            $this->deliveryService->notifyDeliveryStaff($delivery->load('order.items', 'deliveryUser'));

            return $order->fresh();
        });
    }
}
