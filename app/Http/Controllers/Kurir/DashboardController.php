<?php

namespace App\Http\Controllers\Kurir;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard kurir dengan pesanan yang sedang dalam pengiriman.
     */
    public function index()
    {
        $orders = Order::where('status', 'dikirim')
            ->where('kurir_id', auth()->guard('kurir')->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('kurir.dashboard', compact('orders'));
    }

    public function pendingOrders()
    {
        $orders = Order::where('status', 'menunggu_kurir')
            ->orderBy('created_at', 'asc')
            ->get();
            
        return view('kurir.pending_orders', compact('orders'));
    }

    public function showConfirm(Order $order)
    {
        if ($order->status !== 'menunggu_kurir') {
            return redirect()->route('kurir.orders.pending')->with('error', 'Pesanan tidak tersedia untuk dikonfirmasi.');
        }
        
        return view('kurir.confirm_order', compact('order'));
    }

    public function acceptOrder(Request $request, Order $order)
    {
        // Update atomik: cegah dua kurir menerima pesanan yang sama.
        $updated = Order::where('id', $order->id)
            ->where('status', 'menunggu_kurir')
            ->whereNull('kurir_id')
            ->update([
                'status' => 'dikirim',
                'kurir_id' => auth()->guard('kurir')->id(),
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return redirect()->route('kurir.orders.pending')->with('error', 'Pesanan sudah diambil kurir lain atau tidak valid.');
        }

        $order->refresh();

        return redirect()->route('kurir.dashboard')->with('success', 'Berhasil menerima pesanan! Silakan selesaikan pengantaran.');
    }

    /**
     * Kurir mengunggah bukti pengiriman dan menyelesaikan pesanan.
     */
    public function completeDelivery(Request $request, Order $order)
    {
        // Pastikan hanya kurir yang ditugaskan yang boleh menyelesaikan order ini.
        if ((int) $order->kurir_id !== (int) auth()->guard('kurir')->id()) {
            abort(403, 'Anda tidak berhak menyelesaikan pesanan ini.');
        }

        if ($order->status !== 'dikirim') {
            return back()->with('error', 'Pesanan ini tidak sedang dalam pengiriman.');
        }

        $request->validate([
            'bukti_pengiriman' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('bukti_pengiriman')) {
            // Simpan ke storage/app/public agar tetap bisa diakses pada hosting
            // yang document root-nya bukan folder public/ Laravel.
            $fotoPath = $request->file('bukti_pengiriman')->store('uploads/bukti_pengiriman', 'public');
            \App\Support\ImageSanitizer::stripBom($fotoPath);

            $order->update([
                'status' => 'selesai',
                'bukti_pengiriman' => $fotoPath,
            ]);

            $order->refresh();

            return back()->with('success', 'Pesanan #' . $order->id . ' berhasil diselesaikan dan bukti pengiriman telah diunggah!');
        }

        return back()->with('error', 'Gagal mengunggah foto bukti pengiriman.');
    }

    /**
     * Tampilkan riwayat pengiriman kurir.
     */
    public function history()
    {
        $orders = Order::where('status', 'selesai')
            ->where('kurir_id', auth()->guard('kurir')->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('kurir.riwayat_pengiriman', compact('orders'));
    }
}
