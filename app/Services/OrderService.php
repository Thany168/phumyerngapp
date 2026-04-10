<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private TelegramNotificationService $telegram,
        private DeliveryService             $deliveryService,
    ) {}

    public function createOrder(array $data, array $cartItems, int $ownerId): Order
    {
        return DB::transaction(function () use ($data, $cartItems, $ownerId) {
            $total         = 0;
            $resolvedItems = [];

            foreach ($cartItems as $item) {
                $product = Product::where('owner_id', $ownerId)
                    ->where('id', $item['product_id'])
                    ->where('is_available', true)
                    ->firstOrFail();

                $subtotal        = $product->price * $item['quantity'];
                $total          += $subtotal;
                $resolvedItems[] = compact('product', 'subtotal') + ['quantity' => $item['quantity']];
            }

            $order = Order::create([
                'owner_id'             => $ownerId,
                'user_id'              => $data['user_id'],
                'customer_telegram_id' => $data['telegram_id'],
                'customer_name'        => $data['name'],
                'customer_phone'       => $data['phone'],
                'delivery_location'    => $data['location'],
                'status'               => OrderStatus::Pending,
                'total_amount'         => $total,
            ]);

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

            Payment::create(['order_id' => $order->id]);

            // Notify owner
            $owner = $order->owner;
            if ($owner->telegram_chat_id) {
                $this->telegram->notifyOwnerNewOrder(
                    $owner->telegram_chat_id,
                    $order->load('items')
                );
            }

            return $order->load('items.product', 'payment');
        });
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
