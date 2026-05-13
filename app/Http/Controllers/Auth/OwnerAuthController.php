<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OwnerAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'company_code' => 'required|string',
            'phone'        => 'required|string',
            'password'     => 'required|string',
        ]);

        $user = User::where('company_code', $request->company_code)
            ->where('phone', $request->phone)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Unauthorized. Owner access only.'], 403);
        }

        // Single session — revoke old tokens
        $user->tokens()->delete();

        $token = $user->createToken('owner-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->only(['id', 'name', 'company_code', 'phone', 'role']),
            'owner' => $user->owner,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }
}
