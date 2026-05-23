<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OwnerAuthController extends Controller
{
    /**
     * 💼 Merchant Login: Uses Company Code, Phone Number, and Password
     * 🚀 NO OTP OR EMAIL AT ALL!
     */
    public function login(Request $request)
    {
        // Validate incoming data strings
        $request->validate([
            'company_code' => 'required|string',
            'phone'        => 'required|string',
            'password'     => 'required|string',
        ]);

        // 🔍 Locate the merchant user using both company_code and phone parameters
        $user = User::query()
            ->where('company_code', $request->company_code)
            ->where('phone', $request->phone)
            ->first();

        // Ensure user exists, password matches, and role is strictly an owner
        if (!$user || !Hash::check($request->password, $user->password) || $user->role !== 'owner') {
            return response()->json([
                'message' => 'Invalid Company Code, Phone Number, or Password.'
            ], 422);
        }

        // 🌟 Success! Generate token instantly and unlock their merchant workspace immediately
        $token = $user->createToken('owner-panel-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token'  => $token,
            'user'   => [
                'id'           => $user->id,
                'name'         => $user->name,
                'role'         => $user->role,
                'company_code' => $user->company_code,
                'phone'        => $user->phone,
            ]
        ], 200);
    }

    /**
     * 🚪 Owner Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Owner logged out successfully.']);
    }
}
