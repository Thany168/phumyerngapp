<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'owner_id',
        'delivery_user_id',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
    public function deliveryUser()
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }
}
