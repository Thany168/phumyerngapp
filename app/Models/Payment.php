<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'screenshot_path',
        'screenshot_url',
        'status',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected $casts = ['verified_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
