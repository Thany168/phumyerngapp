<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function getOwner(Request $request)
    {
        $owner = $request->user()?->owner;
        if (!$owner) {
            abort(404);
        }
        return $owner;
    }

    protected function ownerId(Request $request): int
    {
        return $this->getOwner($request)->id;
    }
}

