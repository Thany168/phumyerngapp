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
        $search = trim((string) ($request->query('search', '') ?: $request->query('q', '')));
        $searchLower = mb_strtolower($search);

        $categories = Category::where('owner_id', $owner->id)
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

        if ($searchLower !== '') {
            $categories = $categories->filter(fn($category) => $category->products->isNotEmpty())->values();
        }

        return response()->json($categories);
    }
}
