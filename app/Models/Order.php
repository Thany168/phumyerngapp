<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'owner_id',
        'user_id',
        'customer_telegram_id',
        'customer_name',
        'customer_phone',
        'delivery_location',
        'status',
        'total_amount',
        'notes',
        'confirmed_at',
        'delivered_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'status'       => OrderStatus::class,
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
