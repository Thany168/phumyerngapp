<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // We use this to talk to Telegram
use App\Models\Owner;

class TelegramWebhookController extends Controller
{
    protected $botToken;

    public function __construct()
    {
        // Make sure you have TELEGRAM_BOT_TOKEN in your .env file
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
    }

    public function handle(Request $request)
    {
        $update = $request->all();

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'] ?? '';

            if (str_starts_with($text, '/start')) {
                $parts = explode(' ', $text);
                $ownerId = count($parts) > 1 ? $parts[1] : null;

                if ($ownerId && is_numeric($ownerId)) {
                    $owner = Owner::find($ownerId);
                    if ($owner) {
                        return $this->sendShopWelcome($chatId, $owner);
                    }
                }
                return $this->sendDefaultWelcome($chatId);
            }
        }
        return response()->json(['status' => 'success']);
    }

    private function sendShopWelcome($chatId, $owner)
    {
        $botUsername = "phumyerng_bot";
        $appUrl = "https://t.me/{$botUsername}/app?startapp={$owner->id}";

        $text = "Welcome to *{$owner->shop_name}*! 🏪\n\n" .
                "{$owner->shop_description}\n\n" .
                "Click the button below to browse our menu and order.";

        return $this->sendMessage($chatId, $text, [
            'inline_keyboard' => [[
                ['text' => "🛒 Open Menu", 'web_app' => ['url' => $appUrl]]
            ]]
        ]);
    }

    private function sendDefaultWelcome($chatId)
    {
        $text = "Welcome to Phum Yerng! 🚀\n\nPlease use a shop link to see a specific menu.";
        return $this->sendMessage($chatId, $text);
    }

    // This is the missing method that actually talks to Telegram
    private function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return Http::post($url, $data);
    }
}
