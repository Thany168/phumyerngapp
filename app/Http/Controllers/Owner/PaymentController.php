<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = $request->user()->owner->id;
        $payments = Payment::whereHas('order', fn($q) => $q->where('owner_id', $ownerId))
            ->with('order')
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($payments);
    }

    public function uploadScreenshot(Request $request, Payment $payment)
    {
        $request->validate(['screenshot' => 'required|image|max:5120']);
        $path = $request->file('screenshot')->store('payments', 'public');
        $payment->update([
            'screenshot_path' => $path,
            'screenshot_url'  => asset('storage/' . $path),
        ]);
        return response()->json($payment);
    }
}
