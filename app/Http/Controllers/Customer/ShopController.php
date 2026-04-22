<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function show(Owner $owner ) 
    {
        return response()->json([
            'id'          => $owner->id,
            'shop_name'   => $owner->shop_name,
            'description' => $owner->shop_description,
            'logo_url'    => $owner->logo_url,
        ]);
    }
}
