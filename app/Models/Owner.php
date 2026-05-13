<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class Owner extends Model
{
    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_description',
        'telegram_chat_id',
        'telegram_bot_token',
        'logo_url',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
    public function delete()
    {
        return parent::delete();
    }
}
