<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OwnerSettingsController extends Controller
{
    /**
     * Registers a custom bot token for an owner and sets up the webhook dynamically.
     */
    public function registerCustomBot(Request $request, Owner $owner)
    {
        $token = $request->input('bot_token');

        if (!$token) {
            return response()->json(['error' => 'Bot token is required.'], 420);
        }

        // 1. Tell Telegram to send this custom bot's messages to your shared webhook URL
        $webhookUrl = "https://stinging-unknowing-dry.ngrok-free.dev/api/telegram/webhook";

        $response = Http::withoutVerifying()
            ->get("https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}");

        if ($response->failed()) {
            Log::error("Failed to set webhook for custom bot: " . $response->body());
            return response()->json(['error' => 'Invalid bot token or Telegram API error.'], 400);
        }

        // 2. Get bot details (like username) from Telegram API automatically
        $botInfo = Http::withoutVerifying()->get("https://api.telegram.org/bot{$token}/getMe");
        $botUsername = $botInfo->json('result.username');

        // 3. Save it to the owner's profile database row
        $owner->update([
            'telegram_bot_token'    => $token,
            'telegram_bot_username' => $botUsername
        ]);

        return response()->json([
            'success' => 'Custom bot linked successfully!',
            'bot_username' => $botUsername
        ]);
    }
}
