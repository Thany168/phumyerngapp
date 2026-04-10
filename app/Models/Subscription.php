<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'owner_id',
        'plan',
        'status',
        'starts_at',
        'expires_at',
        'amount_paid',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
