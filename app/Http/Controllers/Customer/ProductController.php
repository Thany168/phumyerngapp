<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(Owner $owner)
    {
        $categories = Category::where('owner_id', $owner->id)
            ->where('is_active', true)
            ->with([
                'products' => fn($q) => $q
                    ->where('is_available', true)
                    ->orderBy('sort_order')
            ])
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }
}
