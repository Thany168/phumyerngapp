<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'company_code',      // owner only
        'phone',             // owner only
        'email',             // super_admin only
        'password',          // owner + super_admin
        'telegram_id',       // customer only
        'telegram_username', // customer only
        'role',              // super_admin | owner | customer
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function isCustomer()
    {
        return $this->role === 'customer';
    }
    public function owner()
    {
        return $this->hasOne(Owner::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'delivery_user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
