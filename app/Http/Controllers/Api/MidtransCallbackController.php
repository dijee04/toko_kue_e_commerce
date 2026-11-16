<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransCallbackController extends Controller
{
    public function callback(Request $request)
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = filter_var(config('services.midtrans.is_production'), FILTER_VALIDATE_BOOLEAN);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        try {
            // Notification() memverifikasi signature_key dari Midtrans.
            $notification = new Notification();
        } catch (\Throwable $e) {
            Log::warning('Midtrans callback tidak valid: ' . $e->getMessage());

            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $transaction = $notification->transaction_status;
        $type = $notification->payment_type;
        $orderId = $notification->order_id;
        $fraud = $notification->fraud_status;
        $grossAmount = (float) $notification->gross_amount;

        $order = Order::where('midtrans_order_id', $orderId)->first();

        if (!$order) {
            // Kompatibilitas data lama: order_id lama berformat "{id}-{timestamp}".
            $legacyId = (int) explode('-', (string) $orderId)[0];
            $order = $legacyId > 0 ? Order::find($legacyId) : null;
        }

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Jangan percaya nominal dari notifikasi: pastikan tidak kurang dari total order.
        if ($grossAmount < (float) $order->total_harga) {
            Log::warning('Midtrans gross_amount tidak cocok', [
                'order_id' => $order->id,
                'expected' => $order->total_harga,
                'received' => $grossAmount,
            ]);

            return response()->json(['message' => 'Amount mismatch'], 400);
        }

        $target = $order->payment_status;

        if ($transaction === 'capture') {
            if ($type === 'credit_card') {
                $target = $fraud === 'challenge' ? 'pending' : 'paid';
            }
        } elseif ($transaction === 'settlement') {
            $target = 'paid';
        } elseif ($transaction === 'pending') {
            $target = 'pending';
        } elseif ($transaction === 'deny' || $transaction === 'cancel') {
            $target = 'failed';
        } elseif ($transaction === 'expire') {
            $target = 'expired';
        }

        if ($target === 'paid') {
            // Idempoten: set lunas, pindah status baru → disiapkan, tambah terjual.
            $order->markPaid();
        } elseif ($target !== $order->payment_status) {
            $order->update(['payment_status' => $target]);
        }

        return response()->json(['message' => 'Callback received successfully']);
    }
}
