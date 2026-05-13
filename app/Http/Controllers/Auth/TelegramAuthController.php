<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TelegramAuthController extends Controller
{

    public function login(Request $request)
{

    // 1. Get data from React (using the same key names)
    $initData = $request->input('initData') ?? $request->input('init_data');

    if (!$initData) {
        return response()->json(['message' => 'No initData provided'], 400);
    }
    

    // 2. Parse the string into an array
    $parsed = $this->parseInitData($initData);

    // 3. Validate the Telegram Hash
    if (!$this->validateInitData($parsed)) {
        return response()->json(['message' => 'Invalid Telegram signature'], 401);
    }

    // 4. Extract User info
    $tgUser = json_decode($parsed['user'] ?? '{}', true);
    if (empty($tgUser['id'])) {
        return response()->json(['message' => 'User data missing'], 401);
    }

    // 5. Create or Get User
    $user = User::updateOrCreate(
        ['telegram_id' => (string) $tgUser['id']],
        [
            'name' => trim(($tgUser['first_name'] ?? '') . ' ' . ($tgUser['last_name'] ?? '')),
            'telegram_username' => $tgUser['username'] ?? null,
            'email' => $tgUser['id'] . '@telegram.local',
            'password' => bcrypt(str()->random(32)),
            'role' => 'customer',
        ]
    );

    // 6. Generate Token
    $token = $user->createToken('telegram-miniapp')->plainTextToken;

// 7. Find if this user owns a shop (The "Smart Routing" part)
$owner = \App\Models\Owner::where('user_id', $user->id)->first();

return response()->json([
    'token' => $token,
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ],
    // Return the owner_id so React knows which menu to load
    'owner_id' => $owner ? $owner->id : null
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

    // Lookup owner for Dev Mode
            $owner = \App\Models\Owner::where('user_id', $user->id)->first();

            return response()->json([
                'token' => $token,
                'user' => $user,
                'owner_id' => $owner ? $owner->id : null
            ]);
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

    // Use env() directly if you aren't sure about your config files
    $botToken = env('TELEGRAM_BOT_TOKEN');

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $expected  = hash_hmac('sha256', $dataCheckString, $secretKey);

    return hash_equals($expected, $hash);
}
}
