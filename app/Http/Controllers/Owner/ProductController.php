<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Product::where('owner_id', $request->user()->owner->id)
                ->with('category')->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'  => 'nullable|integer',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'price'        => 'required|numeric|min:0',
            'image_url'    => 'nullable|url',
            'stock'        => 'integer|min:-1',
            'is_available' => 'boolean',
            'sort_order'   => 'integer',
        ]);
        $product = Product::create(array_merge($data, [
            'owner_id' => $request->user()->owner->id,
        ]));
        return response()->json($product->load('category'), 201);
    }

    public function show(Request $request, Product $product)
    {
        $this->checkOwner($request, $product->owner_id);
        return response()->json($product->load('category'));
    }

    public function update(Request $request, Product $product)
    {
        $this->checkOwner($request, $product->owner_id);
        $data = $request->validate([
            'category_id'  => 'nullable|integer',
            'name'         => 'string|max:255',
            'description'  => 'nullable|string',
            'price'        => 'numeric|min:0',
            'image_url'    => 'nullable|url',
            'stock'        => 'integer|min:-1',
            'is_available' => 'boolean',
            'sort_order'   => 'integer',
        ]);
        $product->update($data);
        return response()->json($product->load('category'));
    }

    public function destroy(Request $request, Product $product)
    {
        $this->checkOwner($request, $product->owner_id);
        $product->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function checkOwner(Request $request, int $ownerId): void
    {
        if ($request->user()->owner->id !== $ownerId) abort(403);
    }
}
