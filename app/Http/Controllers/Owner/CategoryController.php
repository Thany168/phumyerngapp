<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Category::query()->where('owner_id', $this->ownerId($request))
                ->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'image_url'  => 'nullable|url',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $category = Category::create(array_merge($data, [
            'owner_id' => $this->ownerId($request),
        ]));
        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->checkOwner($request, $category->owner_id);
        $data = $request->validate([
            'name'       => 'string|max:255',
            'image_url'  => 'nullable|url',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->checkOwner($request, $category->owner_id);
        $category->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function checkOwner(Request $request, int $ownerId): void
    {
        if ($this->ownerId($request) !== $ownerId) abort(403);
    }
}
