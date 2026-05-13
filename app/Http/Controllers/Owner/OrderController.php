<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Owner;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // 1. List all orders for the specific owner
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $owner = $user->owner;

        if (!$owner || !isset($owner->id)) {
            return response()->json([
                'message' => 'Your account does not have an owner profile linked.',
                'tip' => 'Please create a shop first or update your database to link this user ID to a shop owner profile.'
            ], 403);
        }

        $query = Order::whereOwnerId($owner->id)
            ->with(['items.product', 'payment', 'delivery.deliveryUser'])
            ->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    // 2. Get the unique shop link and QR data
    public function getMyLink(Request $request)
    {
        if (!$request->user()->owner) {
            return response()->json(['message' => 'Owner profile not found'], 404);
        }

        $ownerId = $request->user()->owner->id;
        $botUsername = "phumyerng_bot";

        $link = "https://t.me/{$botUsername}/app?startapp={$ownerId}";

        return response()->json([
            'owner_id' => $ownerId,
            'link' => $link,
            'qr_data' => $link
        ]);
    }

    // Generate the setup command for the user to copy
public function getSetupCommand(Request $request)
{
    $owner = $request->user()->owner;

    if (!$owner) {
        return response()->json(['message' => 'Owner profile not found'], 404);
    }

    // We generate a simple unique token linked to the owner's ID
    $setupToken = bin2hex(random_bytes(16)) . $owner->id;

    // Save this setup token temporarily in the database or cache
    $owner->update([
        'telegram_bot_token' => $setupToken, // We temporarily store the token here to verify it later
    ]);

    return response()->json([
        'setup_command' => "/setup " . $setupToken,
        'tip' => "Copy this command and paste it inside your Telegram Group."
    ]);
}

    // 3. Update & Verify Telegram Settings (Saves token and activates webhook)
    public function updateTelegramSettings(Request $request)
{
    $owner = $request->user()->owner;

    if (!$owner) {
        return response()->json(['message' => 'Owner profile not found'], 404);
    }

    $validated = $request->validate([
        'telegram_bot_token' => 'required|string|max:255',
    ]);

    $token = $validated['telegram_bot_token'];

    try {
        // 1. First, delete any active webhook so getUpdates is allowed to work
        Http::get("https://api.telegram.org/bot{$token}/deleteWebhook");

        // 2. Fetch the bot's latest group chat events
        $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates");

        if (!$response->successful()) {
            return response()->json(['message' => 'Invalid Bot Token. Please check it.'], 422);
        }

        $updates = $response->json('result') ?? [];
        $groupId = null;

        // 3. Scan the messages to find the most recent group chat ID
        foreach (array_reverse($updates) as $update) {
            // Check if added to a group/supergroup via my_chat_member
            if (isset($update['my_chat_member']['chat']['id'])) {
                $groupId = $update['my_chat_member']['chat']['id'];
                break;
            }
            // Check fallback standard messages in groups
            elseif (isset($update['message']['chat']['id']) && in_array($update['message']['chat']['type'], ['group', 'supergroup'])) {
                $groupId = $update['message']['chat']['id'];
                break;
            }
        }

        // 4. If no group was detected in the history
        if (!$groupId) {
            return response()->json([
                'message' => 'Bot token is valid, but we could not detect your bot in any group. Please add the bot to your Telegram group first, send a test message, and click Verify again.'
            ], 422);
        }

        // 5. Update PostgreSQL DB
        $owner->update([
            'telegram_bot_token' => $token,
            'telegram_chat_id' => (string) $groupId
        ]);

        // 6. Send the direct success message to the group
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $groupId,
            'text' => "🎉 *Verification Success!*\n🏪 Shop *{$owner->shop_name}* is now linked to this group.\nYou will receive real-time order alerts here.",
            'parse_mode' => 'Markdown'
        ]);

        return response()->json([
            'message' => 'Success! Bot linked and test message sent.',
            'owner' => $owner
        ]);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Connection error: ' . $e->getMessage()], 500);
    }
}

    // 4. Handle Telegram Webhook (Saves the Group ID automatically)
public function handleBotWebhook(Request $request)
{
    $update = $request->all();

    if (isset($update['message']['text'])) {
        $text = $update['message']['text'];
        $chatId = $update['message']['chat']['id'];

        if (str_starts_with($text, '/setup ')) {
            $tokenInput = trim(str_replace('/setup ', '', $text));

            // Use array syntax to keep the VS Code IDE clean of false errors

            $owner = \Illuminate\Support\Facades\DB::table('owners')
            ->where('telegram_bot_token', $tokenInput)
            ->first();
            if ($owner) {
                // 🛑 Super Admin Check: Stop the setup if the shop is suspended
                if ($owner->status === 'suspended') {
                    Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "⚠️ *Setup Failed!*\nThis shop profile has been suspended by the platform administrator.",
                        'parse_mode' => 'Markdown'
                    ]);

                    return response()->json(['status' => 'suspended_blocked'], 403);
                }

                // 1. Link the group chat ID
                $owner->update([
                    'telegram_chat_id' => (string) $chatId,
                ]);

                // 2. Reply instantly in the group chat
                Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "✅ *Verification Success!*\n🏪 Shop *{$owner->shop_name}* is now linked to this group.\nYou will receive real-time order alerts here.",
                    'parse_mode' => 'Markdown'
                ]);

                return response()->json(['status' => 'success']);
            }
        }
    }

    return response()->json(['status' => 'ignored']);
}
    // 5. View a specific order
    public function show(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json($order->load('items.product', 'payment', 'delivery.deliveryUser'));
    }

    // 6. Confirm an order
    public function confirm(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        return response()->json(
            $this->orderService->confirmOrder($order, $request->user()->id)
        );
    }

    // 7. Reject an order
    public function reject(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['reason' => 'nullable|string|max:500']);
        return response()->json(
            $this->orderService->rejectOrder($order, $request->input('reason', ''))
        );
    }

    // 8. Assign order to delivery staff
    public function assignDelivery(Request $request, Order $order)
    {
        $this->checkOwner($request, $order);
        $request->validate(['delivery_user_id' => 'required|integer|exists:users,id']);
        return response()->json(
            $this->orderService->assignDelivery($order, $request->delivery_user_id)
        );
    }

    // 9. Fetch list of delivery staff
    public function deliveryStaff(Request $request)
    {
        $staff = DB::table('users')->where('role', 'delivery')->select('id', 'name', 'telegram_username')->get();
        return response()->json($staff);
    }

    // Security check: Ensure owner only interacts with their own orders
    private function checkOwner(Request $request, Order $order): void
    {
        $user = $request->user();

        if (!$user || !$user->owner || $order->owner_id !== $user->owner->id) {
            abort(403);
        }
    }
}
