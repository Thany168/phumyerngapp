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
        $categories = Category::query()->where('owner_id', $owner->id)
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

        // 4. 🎯 THE DYNAMIC URL NORMALIZER FOR MINI APP VIEW LAYERS
        $categories->transform(function ($category) {
            $category->products->transform(function ($product) {
                // Combine multi-key fallbacks to guarantee path string collection
                $path = $product->image_url ?? $product->image ?? $product->photo;

                if (!$path) {
                    $product->image_url = "https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=300";
                    return $product;
                }

                // If it's a solid absolute link string (Cloudinary uploads), keep it raw!
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $product->image_url = $path;
                } else {
                    $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $path), '/');
                    // Route using absolute fallback server connection addressing lines
                    $product->image_url = url('/api/media') . '?path=' . urlencode($cleanPath) . '&ngrok-skip-browser-warning=true';
                }

                return $product;
            });
            return $category;
        });

        return response()->json($categories);
    }
}
