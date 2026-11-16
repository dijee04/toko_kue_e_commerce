<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Keranjang;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Support\ProdukOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'no_wa' => 'required|string|max:20',
            'alamat' => 'required|string|max:1000',
            'items' => 'required',
            'ongkir' => 'nullable|numeric|min:0|max:100000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $rawItems = is_string($request->items)
            ? json_decode($request->items, true)
            : $request->items;

        if (!is_array($rawItems) || empty($rawItems)) {
            return response()->json(['success' => false, 'message' => 'Keranjang kosong'], 422);
        }

        // Jangan pernah percaya harga dari browser: ambil ulang dari database.
        $items = [];
        foreach ($rawItems as $item) {
            $produkId = (int) ($item['id'] ?? $item['produk_id'] ?? 0);
            $jumlah = (int) ($item['quantity'] ?? $item['jumlah'] ?? 0);

            if ($produkId <= 0 || $jumlah <= 0) {
                return response()->json(['success' => false, 'message' => 'Item pesanan tidak valid'], 422);
            }

            $produk = Produk::with('kategori')->where('is_active', true)->where('harga', '>', 0)->find($produkId);
            if (!$produk) {
                return response()->json(['success' => false, 'message' => 'Produk tidak tersedia untuk dipesan'], 422);
            }

            $selections = isset($item['selections']) && is_array($item['selections'])
                ? $item['selections']
                : [];

            $unitPrice = ProdukOptions::unitPrice($produk, $selections);
            if ($unitPrice === null) {
                return response()->json(['success' => false, 'message' => 'Pilihan produk tidak valid'], 422);
            }

            $items[] = [
                'produk_id' => $produk->id,
                'jumlah' => $jumlah,
                'harga_satuan' => (float) $unitPrice,
            ];
        }

        $subtotal = collect($items)->sum(fn ($i) => $i['harga_satuan'] * $i['jumlah']);
        $ongkir = (float) ($validated['ongkir'] ?? 0);
        $total = $subtotal + $ongkir;

        // Titik koordinat: pakai dari peta checkout bila ada, jika tidak
        // fallback ke koordinat yang tersimpan di profil pelanggan.
        $user = auth()->user();
        $latitude = $validated['latitude'] ?? $user->latitude ?? null;
        $longitude = $validated['longitude'] ?? $user->longitude ?? null;

        try {
            DB::beginTransaction();

            $order = Order::create([
                'user_id' => auth()->id(),
                'nama_pelanggan' => $validated['nama_pelanggan'],
                'no_wa' => $validated['no_wa'],
                'alamat' => $validated['alamat'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'metode_pembayaran' => 'Midtrans',
                'ongkir' => $ongkir,
                'total_harga' => $total,
                'status' => 'baru',
                'payment_status' => 'unpaid',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'produk_id' => $item['produk_id'],
                    'jumlah' => $item['jumlah'],
                    'harga_satuan' => $item['harga_satuan'],
                ]);
            }

            // Keranjang sudah dipindahkan menjadi order.
            Keranjang::where('user_id', auth()->id())->delete();

            Config::$serverKey = config('services.midtrans.server_key');
            Config::$isProduction = filter_var(config('services.midtrans.is_production'), FILTER_VALIDATE_BOOLEAN);
            Config::$isSanitized = true;
            Config::$is3ds = true;

            $midtransOrderId = 'ORDER-' . $order->id . '-' . time();

            $params = [
                'transaction_details' => [
                    'order_id' => $midtransOrderId,
                    'gross_amount' => (int) round($total),
                ],
                'customer_details' => [
                    'first_name' => $order->nama_pelanggan,
                    'phone' => $order->no_wa,
                    'billing_address' => [
                        'address' => $order->alamat,
                    ],
                ],
            ];

            $snapToken = Snap::getSnapToken($params);

            $order->update([
                'snap_token' => $snapToken,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'snap_token' => $snapToken,
                'total_harga' => $total,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Checkout gagal: ' . $e->getMessage(), ['user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pesanan. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Sinkronisasi status pembayaran setelah Snap popup.
     * TIDAK memercayai browser: status dicek ulang ke API Midtrans.
     */
    public function successLocal(Request $request)
    {
        $request->validate(['snap_token' => 'required|string']);

        $order = Order::where('snap_token', $request->snap_token)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['success' => true]);
        }

        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = filter_var(config('services.midtrans.is_production'), FILTER_VALIDATE_BOOLEAN);

        try {
            $status = Transaction::status($order->midtrans_order_id ?: $order->id);
        } catch (\Throwable $e) {
            Log::warning('Verifikasi pembayaran gagal: ' . $e->getMessage(), ['order_id' => $order->id]);

            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat memverifikasi pembayaran saat ini.',
            ], 422);
        }

        $transactionStatus = is_array($status) ? ($status['transaction_status'] ?? null) : ($status->transaction_status ?? null);
        $grossAmount = is_array($status) ? ($status['gross_amount'] ?? 0) : ($status->gross_amount ?? 0);

        $isPaid = in_array($transactionStatus, ['settlement', 'capture'], true)
            && (float) $grossAmount >= (float) $order->total_harga;

        if ($isPaid) {
            // Idempoten: set lunas, pindah status baru → disiapkan, tambah terjual.
            $order->markPaid();

            return response()->json(['success' => true]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Pembayaran belum terverifikasi oleh Midtrans.',
        ], 422);
    }
}
