<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
