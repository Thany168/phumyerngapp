<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Owner extends Model
{
    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_description',
        'telegram_chat_id',
        'logo_url',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
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
        return $this->hasOne(Subscription::class, 'owner_id');
    }
    public function delete()
    {
        $this->products()->delete();
        $this->orders()->delete();
        $this->deliveries()->delete();
        $this->subscription()->delete();
        return parent::delete();
    }
}
