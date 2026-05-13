<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderService
{
    public function confirmOrder(Order $order, $userId)
    {
        // TODO: Implement order confirmation logic
        return ['message' => 'Order confirmed'];
    }

    public function rejectOrder(Order $order, $reason)
    {
        // TODO: Implement order rejection logic
        return ['message' => 'Order rejected'];
    }

    public function assignDelivery(Order $order, $deliveryUserId)
    {
        // TODO: Implement delivery assignment logic
        return ['message' => 'Delivery assigned'];
    }
}
