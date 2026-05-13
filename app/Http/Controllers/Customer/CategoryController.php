<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Owner;

class CategoryController extends Controller
{
    public function index($ownerId)
    {
        $owner = Owner::findOrFail($ownerId);

        return response()->json(
            Category::where('owner_id', $owner->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }
}
