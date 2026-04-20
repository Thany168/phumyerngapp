<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TelegramAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['init_data' => 'required|string']);

        $parsed = $this->parseInitData($request->input('init_data'));

        if (!$this->validateInitData($parsed)) {
            return response()->json(['message' => 'Invalid Telegram data'], 401);
        }

        $tgUser = json_decode($parsed['user'] ?? '{}', true);

        if (empty($tgUser['id'])) {
            return response()->json(['message' => 'No user in init data'], 401);
        }

        $user = User::firstOrCreate(
            ['telegram_id' => (string) $tgUser['id']],
            [
                'name'              => trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')),
                'telegram_username' => $tgUser['username'] ?? null,
                'email'             => $tgUser['id'] . '@telegram.local',
                'password'          => bcrypt(str()->random(32)),
                'role'              => 'customer',
            ]
        );

        $user->update([
            'name'              => trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')),
            'telegram_username' => $tgUser['username'] ?? null,
        ]);

        $token = $user->createToken('telegram-miniapp')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'role'     => $user->role,
                'telegram' => $user->telegram_username,
            ],
        ]);
    }

    // For testing without real Telegram — creates/finds user by telegram_id directly
    public function loginDev(Request $request)
    {
        if (app()->environment('production')) {
            return response()->json(['message' => 'Not available'], 404);
        }

        $request->validate([
            'telegram_id'       => 'required|string',
            'name'              => 'required|string',
            'role'              => 'in:customer,owner,delivery,super_admin',
        ]);

        $user = User::firstOrCreate(
            ['telegram_id' => $request->telegram_id],
            [
                'name'     => $request->name,
                'email'    => $request->telegram_id . '@telegram.local',
                'password' => bcrypt('password'),
                'role'     => $request->role ?? 'customer',
            ]
        );

        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    private function parseInitData(string $initData): array
    {
        $result = [];
        foreach (explode('&', $initData) as $chunk) {
            [$key, $value] = array_pad(explode('=', $chunk, 2), 2, '');
            $result[urldecode($key)] = urldecode($value);
        }
        return $result;
    }

    private function validateInitData(array $parsed): bool
    {
        $hash = $parsed['hash'] ?? '';
        unset($parsed['hash']);
        ksort($parsed);

        $dataCheckArr = [];
        foreach ($parsed as $key => $value) {
        $dataCheckArr[] = "$key=$value";
        }

        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash_hmac('sha256', config('telegram.bot_token'), 'WebAppData', true);
        $expected  = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($expected, $hash);
    }
}
