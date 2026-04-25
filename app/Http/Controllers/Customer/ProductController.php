<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, Owner $owner)
    {

        // 1. Get search term (if any)
    $search = trim((string) ($request->query('search', '') ?: $request->query('q', '')));
    $searchLower = mb_strtolower($search);
    
    // 2. Filter Categories and Products ONLY for THIS specific owner
    $categories = Category::where('owner_id', $owner->id) // Use the ID from the route parameter
        ->where('is_active', true)
        ->with(['products' => function ($query) use ($searchLower) {
            $query->where('is_available', true)
                  ->orderBy('sort_order');

            if ($searchLower !== '') {
                $query->where(function ($query) use ($searchLower) {
                    $query->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                          ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$searchLower}%"]);
                });
            }
        }])
        ->orderBy('sort_order')
        ->get();

    // 3. Remove categories that are empty if searching
    if ($searchLower !== '') {
        $categories = $categories->filter(fn($category) => $category->products->isNotEmpty())->values();
    }

    return response()->json($categories);
}
}
