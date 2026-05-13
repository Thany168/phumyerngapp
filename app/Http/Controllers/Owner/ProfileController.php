<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // GET /owner/profile
    public function show(Request $request)
    {
        $owner = $request->user()->owner;

        if (!$owner) {
            return response()->json(['message' => 'Owner profile not found.'], 404);
        }

        return response()->json($this->formatOwner($owner));
    }

    // POST /owner/profile
    public function update(Request $request)
    {
        $owner = $request->user()->owner;

        if (!$owner) {
            return response()->json(['message' => 'Owner profile not found.'], 404);
        }

        $request->validate([
            'shop_name'        => 'sometimes|string|max:255',
            'shop_description' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string|max:100',
            'logo'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $payload = [];

        if ($request->filled('shop_name'))
            $payload['shop_name'] = $request->shop_name;

        if ($request->has('shop_description'))
            $payload['shop_description'] = $request->shop_description;

        if ($request->has('telegram_chat_id'))
            $payload['telegram_chat_id'] = $request->telegram_chat_id;

        if ($request->hasFile('logo')) {
            $oldPath = $owner->getAttributes()['logo_url'] ?? null;
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $payload['logo_url'] = $request->file('logo')->store('logos', 'public');
        }

        $owner->update($payload);

        // Re-fetch fresh from DB to get the saved logo_url
        $fresh = $owner->fresh();

        \Log::info('ProfileController@update', [
            'logo_raw'       => $fresh->getAttributes()['logo_url'] ?? 'NULL',
            'storage_url'    => $fresh->getAttributes()['logo_url']
                ? Storage::disk('public')->url($fresh->getAttributes()['logo_url'])
                : 'NULL',
        ]);

        return response()->json($this->formatOwner($fresh));
    }

    // POST /owner/change-password
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // ── helper ────────────────────────────────────────────────────────────────

    private function formatOwner($owner): array
    {
        $data    = $owner->toArray();
        $rawPath = $owner->getAttributes()['logo_url'] ?? null;

        $data['logo_url'] = $rawPath
            ? config('app.url') . '/storage/' . $rawPath
            : null;

        return $data;
    }
}
