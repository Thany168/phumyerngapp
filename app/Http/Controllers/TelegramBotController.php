<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Owner;

class TelegramBotController extends Controller
{
    /**
     * Handle incoming webhooks from your main @phumyerng_bot
     */
    public function handleWebhook(Request $request)
    {
        try {
            $update = $request->all();

            if (!isset($update['message']['text'])) {
                return response()->json(['status' => 'ignored_no_text']);
            }

            $text = trim($update['message']['text']);
            $chatId = $update['message']['chat']['id'];

            if (str_starts_with($text, '/setup')) {
                $parts = explode(' ', $text);

                if (count($parts) < 2) {
                    $this->sendReply($chatId, "⚠️ Invalid Format. Please use: /setup YOUR-TOKEN");
                    return response()->json(['status' => 'missing_token']);
                }

                $providedToken = trim($parts[1]);

                $owner = Owner::query()
                    ->where('telegram_verification_token', $providedToken)
                    ->first();

                if (!$owner) {
                    $this->sendReply($chatId, "❌ Setup Aborted: This token is invalid or expired.");
                    return response()->json(['status' => 'token_not_found']);
                }

                // 🌟 Write the group chat ID permanently to this owner
                $owner->update([
                    'telegram_chat_id'            => (string)$chatId,
                    'telegram_verification_token' => null, // Burn token cleanly
                ]);

                // 🌟 SHARED BOT FIX: Link always points to your main @phumyerng_bot short link!
                $customerBotLink = "https://t.me/phumyerng_bot?startapp={$owner->id}";

                $msg = "🎉 <b>PhumYerng Connection Successful!</b>\n\n";
                $msg .= "🏪 Shop: <b>" . $owner->shop_name . "</b>\n";
                $msg .= "📢 Group Status: Active Alert Feed\n\n";
                $msg .= "🚀 <b>Your Customer Order Link is live!</b>\n";
                $msg .= "Copy and send this link to your customers to accept orders:\n";
                $msg .= "<code>" . $customerBotLink . "</code>";

                $this->sendReply($chatId, $msg);

                return response()->json(['status' => 'successfully_linked']);
            }

            return response()->json(['status' => 'text_ignored']);

        } catch (\Exception $e) {
            Log::error('Telegram Webhook Crash Intercepted: ' . $e->getMessage());
            return response()->json(['status' => 'error_caught_gracefully'], 200);
        }
    }

    /**
     * Always send replies using your primary global bot token
     */
    private function sendReply($chatId, string $text)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id'    => (string)$chatId,
                'text'       => $text,
                'parse_mode' => 'HTML'
            ]);

        if ($response->failed()) {
            Log::error("❌ Telegram API Error: " . $response->body());
        }
    }
}
