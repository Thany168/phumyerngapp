<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'owner_id',
        'category_id',
        'name',
        'description',
        'price',
        'image_url',
        'image_public_id', // 🚀 Mass-assignment tracker flag allowed!
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function booted()
    {
        static::deleting(function (self $product) {
            if ($product->orderItems()->exists()) {
                $product->orderItems()->update(['product_id' => null]);
            }
        });
    }

    public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return "https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=300";
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $value), '/');
        return url('/api/media') . '?path=' . urlencode($cleanPath) . '&ngrok-skip-browser-warning=true';
    }

    /**
     * 🎯 FIXED: Local storage file deletion barriers removed completely.
     * Database rows will now drop cleanly without throwing any exceptions!
     */
    public function delete()
    {
        return parent::delete();
    }
}
