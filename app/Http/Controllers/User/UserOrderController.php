<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Review;

class UserOrderController extends Controller
{
    public function notifikasi()
    {
        $latest_orders = Order::where('user_id', auth()->id())
            ->with(['items.produk'])
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();
            
        return view('user.notifikasi', compact('latest_orders'));
    }

    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.produk', 'reviews.produk'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Sebuah order masih butuh ulasan jika sudah selesai tetapi
        // ada produk di dalamnya yang belum diulas.
        $needsReview = function (Order $order) {
            if ($order->status !== 'selesai') {
                return false;
            }

            $reviewedProductIds = $order->reviews->pluck('produk_id')->filter()->unique();
            $orderedProductIds = $order->items->pluck('produk_id')->unique();

            return $orderedProductIds->diff($reviewedProductIds)->isNotEmpty();
        };

        $active_orders = $orders->filter(function (Order $order) use ($needsReview) {
            return in_array($order->status, ['baru', 'disiapkan', 'menunggu_kurir', 'dikirim'], true)
                || $needsReview($order);
        })->values();

        $completed_orders = $orders->filter(function (Order $order) use ($needsReview) {
            return $order->status === 'dibatalkan'
                || ($order->status === 'selesai' && !$needsReview($order));
        })->values();

        return view('user.pesanan_saya', compact('active_orders', 'completed_orders'));
    }

    public function riwayat()
    {
        return redirect()->route('pesanan_saya', ['tab' => 'riwayat']);
    }

    public function completeOrder(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== 'dikirim') {
            return back()->with('error', 'Pesanan hanya dapat diselesaikan jika sudah dikirim.');
        }

        $order->update([
            'status' => 'selesai'
        ]);

        $order->refresh();

        return redirect()->route('pesanan_saya', ['tab' => 'aktif'])->with('success', 'Pesanan telah diselesaikan! Silakan berikan ulasan Anda di bawah.');
    }

    public function checkStatus()
    {
        $orders = Order::where('user_id', auth()->id())
            ->select('id', 'status')
            ->get();
            
        return response()->json($orders);
    }

    public function nota(Order $order)
    {
        // Pastikan order milik user yang login
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load('items.produk');
        return view('user.order.nota', compact('order'));
    }

    public function storeReview(Request $request, Order $order, Produk $produk)
    {
        if ((int) $order->user_id !== (int) auth()->id() || $order->status !== 'selesai') {
            abort(403);
        }

        // Produk yang diulas harus benar-benar ada di pesanan ini.
        if (!$order->items()->where('produk_id', $produk->id)->exists()) {
            abort(404, 'Produk tidak ditemukan pada pesanan ini.');
        }

        // Satu produk hanya boleh diulas sekali per pesanan.
        if ($order->reviews()->where('produk_id', $produk->id)->exists()) {
            return back()->with('error', 'Produk ini sudah pernah Anda ulas untuk pesanan ini.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'ulasan' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $filename = $file->hashName();
            if (!file_exists(public_path('uploads/reviews'))) {
                mkdir(public_path('uploads/reviews'), 0755, true);
            }
            $file->move(public_path('uploads/reviews'), $filename);
            $fotoPath = 'uploads/reviews/' . $filename;
        }

        Review::create([
            'user_id' => auth()->id(),
            'order_id' => $order->id,
            'produk_id' => $produk->id,
            'rating' => $validated['rating'],
            'ulasan' => $validated['ulasan'] ?? null,
            'foto' => $fotoPath
        ]);

        // Jaga kolom rating_avg produk tetap sinkron dengan ulasan asli.
        $produk->update([
            'rating_avg' => round((float) Review::where('produk_id', $produk->id)->avg('rating'), 2),
        ]);

        $reviewedProductIds = Review::where('order_id', $order->id)->pluck('produk_id');
        $hasRemaining = $order->items()->whereNotIn('produk_id', $reviewedProductIds)->exists();

        return redirect()
            ->route('pesanan_saya', ['tab' => $hasRemaining ? 'aktif' : 'riwayat'])
            ->with('success', 'Terima kasih atas ulasan Anda!');
    }

    public function cancelOrder(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        // Batal pesanan hanya jika status masih 'baru' (belum disiapkan/dikirim)
        if ($order->status !== 'baru') {
            return back()->with('error', 'Pesanan tidak dapat dibatalkan karena sedang diproses atau sudah dikirim.');
        }

        $order->update([
            'status' => 'dibatalkan',
            'payment_status' => 'failed'
        ]);

        $order->refresh();

        return redirect()->route('pesanan_saya', ['tab' => 'riwayat'])->with('success', 'Pesanan Anda telah berhasil dibatalkan.');
    }
}
