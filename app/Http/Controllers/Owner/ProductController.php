<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = $this->ownerId($request);
        return response()->json(Product::query()->where('owner_id', $ownerId)->with('category')->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            'category_id'  => 'nullable',
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'price'        => 'required|numeric|min:0',
            'image_url'    => 'nullable',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'stock'        => 'integer|min:-1',
            'is_available' => 'nullable',
            'sort_order'   => 'integer',
        ]);

        $validate['is_available'] = $request->input('is_available') === '0' ? false : true;
        $validate['category_id'] = (!isset($validate['category_id']) || $validate['category_id'] === '' || $validate['category_id'] === 'null') ? null : (int)$validate['category_id'];

        $validate['image_url'] = "https://images.unsplash.com/photo-1554118811-1e0d58224f24?q=80&w=300";
        $validate['image_public_id'] = null;

        if ($request->filled('image_url') && !$request->hasFile('image') && !$request->hasFile('image_url')) {
            $validate['image_url'] = $request->input('image_url');
            $validate['image_public_id'] = null;
        }

        $imageField = $this->getFirstUploadedImageField($request);

        if ($imageField && $request->file($imageField)->isValid()) {
            try {
                $upload = Cloudinary::uploadApi()->upload($request->file($imageField)->getRealPath(), [
                    'upload_preset' => config('cloudinary.upload_preset', 'snaporder-preset'),
                    'folder' => config('cloudinary.folder', 'snaporder-preset'),
                    'resource_type' => 'image',
                ]);

                if (isset($upload['secure_url'])) {
                    $validate['image_url'] = $upload['secure_url'];
                    $validate['image_public_id'] = $upload['public_id'] ?? null;
                }
            } catch (\Exception $e) {
                \Log::error('Cloudinary Store Exception: ' . $e->getMessage());
                // Fallback to storing the image locally in public/products
                $path = $request->file($imageField)->store('products', 'public');
                $validate['image_url'] = $path;
                $validate['image_public_id'] = null;
            }
        }

        $product = Product::create(array_merge($validate, ['owner_id' => $this->ownerId($request)]));
        return response()->json($product->load('category'), 201);
    }

    public function show(Request $request, $id)
    {
        $ownerId = $this->ownerId($request);
        $product = Product::query()->where('owner_id', $ownerId)->where('id', $id)->firstOrFail();
        return response()->json($product->toArray());
    }

    public function update(Request $request, $id)
    {
        $ownerId = $this->ownerId($request);
        $product = Product::query()->where('owner_id', $ownerId)->where('id', $id)->firstOrFail();

        $validate = $request->validate([
            'category_id'       => 'nullable',
            'name'              => 'string|max:255',
            'description'       => 'nullable|string',
            'price'             => 'numeric|min:0',
            'image_url'         => 'nullable',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'current_image_url' => 'nullable|string',
            'stock'             => 'integer|min:-1',
            'is_available'      => 'nullable',
            'sort_order'        => 'integer',
        ]);

        $validate['is_available'] = $request->input('is_available') === '0' ? false : true;
        $validate['category_id'] = (!isset($validate['category_id']) || $validate['category_id'] === '' || $validate['category_id'] === 'null') ? null : (int)$validate['category_id'];
        $validate['image_url'] = $request->input('current_image_url', $product->getRawOriginal('image_url'));
        $validate['image_public_id'] = $product->image_public_id;

        if ($request->filled('image_url') && !$request->hasFile('image')) {
            $validate['image_url'] = $request->input('image_url');
            $validate['image_public_id'] = null;
        }

        $imageField = $this->getFirstUploadedImageField($request);

        if ($imageField && $request->file($imageField)->isValid()) {
            try {
                $oldPublicId = $product->image_public_id;
                if ($oldPublicId && trim($oldPublicId) !== '' && !str_starts_with($oldPublicId, 'http')) {
                    Cloudinary::uploadApi()->destroy($oldPublicId);
                }

                $upload = Cloudinary::uploadApi()->upload($request->file($imageField)->getRealPath(), [
                    'upload_preset' => config('cloudinary.upload_preset', 'snaporder-preset'),
                    'folder' => config('cloudinary.folder', 'snaporder-preset'),
                    'resource_type' => 'image',
                ]);

                if (isset($upload['secure_url'])) {
                    $validate['image_url'] = $upload['secure_url'];
                    $validate['image_public_id'] = $upload['public_id'] ?? null;
                }
            } catch (\Exception $e) {
                \Log::error('UploadApi Update Exception: ' . $e->getMessage());
                // Fallback to storing the image locally in public/products
                $path = $request->file($imageField)->store('products', 'public');
                $validate['image_url'] = $path;
                $validate['image_public_id'] = null;
            }
        } else {
            $validate['image_public_id'] = $product->image_public_id;
        }

        unset($validate['current_image_url']);
        $product->update($validate);
        return response()->json($product->load('category'));
    }

    protected function getFirstUploadedImageField(Request $request): ?string
    {
        foreach ($request->files->all() as $field => $file) {
            if (!$file) {
                continue;
            }

            if (is_array($file)) {
                $file = reset($file);
            }

            if ($file && $file->isValid() && str_starts_with($file->getMimeType(), 'image/')) {
                return $field;
            }
        }

        return null;
    }

    public function destroy(Request $request, $id)
    {
        $ownerId = $this->ownerId($request);
        $product = Product::query()->where('owner_id', $ownerId)->where('id', $id)->firstOrFail();

        // 🎯 BULLETPROOF DESTROY METHOD: Uses the requested Cloudinary Facade safely inside a try-catch block
        try {
            $oldPublicId = $product->image_public_id;
            if ($oldPublicId && trim($oldPublicId) !== '' && !str_starts_with($oldPublicId, 'http')) {
                Cloudinary::destroy($oldPublicId);
            }
        } catch (\Exception $e) {
            \Log::error('Cloudinary Facade Destroy Safe Intercept: ' . $e->getMessage());
            // Safe bypass prevents connection timeouts or configuration typos from freezing product removal cascades!
        }

        try {
            $product->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'foreign key') || str_contains($message, 'constraint')) {
                // If the product is referenced by order_items, preserve the order snapshots
                // while removing the deleted product reference.
                $product->orderItems()->update(['product_id' => null]);
                $product->delete();
            } else {
                throw $e;
            }
        }

        return response()->json(['message' => 'Deleted successfully']);
    }
}
