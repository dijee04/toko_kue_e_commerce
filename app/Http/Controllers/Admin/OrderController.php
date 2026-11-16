<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::latest()->paginate(15);
        return view('admin.order.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['items.produk', 'reviews.user', 'reviews.produk']);
        return view('admin.order.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:baru,disiapkan,menunggu_kurir,dikirim,selesai,dibatalkan',
            'ongkir' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:unpaid,paid,failed,expired',
        ]);

        $current = $order->status;
        $target = $validated['status'];
        $currentPayment = $order->payment_status;
        $targetPayment = $validated['payment_status'] ?? null;

        if ($order->isFinalStatus() && !$request->filled('payment_status')) {
            return back()->with('error', 'Pesanan berstatus "' . $order->statusLabel() . '" tidak dapat diubah lagi.');
        }

        $isSameStatus = $target === $current;
        $isSamePayment = ($targetPayment === null) || ($targetPayment === $currentPayment);

        // Admin hanya boleh memperbarui ongkir tanpa pindah status ketika
        // pesanan masih menunggu kurir dan belum diambil siapa pun.
        $canUpdateOngkirOnly = $isSameStatus
            && $current === 'menunggu_kurir'
            && $order->kurir_id === null
            && $request->filled('ongkir');

        // Tidak ada apa-apa yang diubah
        if ($isSameStatus && $isSamePayment && !$canUpdateOngkirOnly) {
            return back()->with('error', 'Tidak ada perubahan yang dilakukan.');
        }

        if (!$isSameStatus && !$order->canTransitionTo($target)) {
            return back()->with('error', 'Perubahan status dari "' . $order->statusLabel() . '" ke "' . Order::statusLabelFor($target) . '" tidak diizinkan. Status "Dikirim" dan "Selesai" hanya bisa diubah oleh kurir.');
        }

        $data = [];

        if (!$isSameStatus) {
            $data['status'] = $target;
        }

        if (!$isSamePayment) {
            $data['payment_status'] = $targetPayment;
        }

        if ($target === 'menunggu_kurir' && $request->filled('ongkir')) {
            $ongkir = (float) $validated['ongkir'];
            $subtotal = $order->items->sum(fn ($item) => (float) $item->harga_satuan * (int) $item->jumlah);

            $data['ongkir'] = $ongkir;
            $data['total_harga'] = round($subtotal + $ongkir, 2);
        }

        // Update atomik: jangan menimpa perubahan yang dilakukan kurir
        // pada saat yang sama (mis. kurir menerima pesanan).
        $query = Order::where('id', $order->id)->where('status', $current);

        if ($current === 'menunggu_kurir') {
            $query->whereNull('kurir_id');
        }

        $updated = $query->update($data + ['updated_at' => now()]);

        if (!$updated) {
            return back()->with('error', 'Data pesanan sudah berubah. Muat ulang halaman lalu coba lagi.');
        }

        $messages = [];
        if (!$isSameStatus) {
            $messages[] = 'status pesanan ke "' . Order::statusLabelFor($target) . '"';
        }
        if (!$isSamePayment) {
            $messages[] = 'pembayaran ke "' . ucfirst($targetPayment) . '"';
        }
        $pesan = implode(' dan ', $messages);

        return back()->with('success', $pesan . ' berhasil diperbarui!');
    }
}
