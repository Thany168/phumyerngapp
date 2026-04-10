<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // 
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    protected $fillable = [
        'name',
        'email',
        'telegram_id',
        'telegram_username',
        'role',
        'password',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    /**
     * 
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
