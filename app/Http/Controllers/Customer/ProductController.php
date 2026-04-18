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
        $search = trim((string) $request->query('search', ''));

        $categories = Category::where('owner_id', $owner->id)
            ->where('is_active', true)
            ->with(['products' => function ($query) use ($search) {
                $query->where('is_available', true)
                    ->orderBy('sort_order');

                if ($search !== '') {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                              ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            }])
            ->orderBy('sort_order')
            ->get();

        if ($search !== '') {
            $categories = $categories->filter(fn($category) => $category->products->isNotEmpty())->values();
        }

        return response()->json($categories);
    }
}
