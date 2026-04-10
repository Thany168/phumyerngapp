<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'owner_id',
        'category_id',
        'name',
        'description',
        'price',
        'image_url',
        'stock',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_available' => 'boolean',
        'stock'        => 'integer',
        'sort_order'   => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
