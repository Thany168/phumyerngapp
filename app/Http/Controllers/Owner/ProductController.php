<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class ProductController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = $this->ownerId($request);
        return response()->json(
            Product::query()->where('owner_id', $ownerId)
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
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'stock'        => 'integer|min:-1',
            'is_available' => 'boolean',
            'sort_order'   => 'integer',
        ]);
        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create(array_merge($data, [
            'owner_id' => $this->ownerId($request),
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
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'stock'        => 'integer|min:-1',
            'is_available' => 'boolean',
            'sort_order'   => 'integer',
        ]);

        if ($request->hasFile('image')) {

            $oldPath = $product->getRawOriginal('image_url');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            $data['image_url'] = $request->file('image')->store('products', 'public');
        }

        unset($data['image']);
        $product->update($data);
        return response()->json($product->load('category'));
    }

    public function destroy(Request $request, Product $product)
    {
        $this->checkOwner($request, $product->owner_id);

        $oldPath = $product->getRawOriginal('image_url');
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $product->delete();
        return response()->json(['message' => 'Deleted']);
    }
    private function checkOwner(Request $request, int $ownerId): void
    {
        if ($this->ownerId($request) !== $ownerId) abort(403);
    }
}
