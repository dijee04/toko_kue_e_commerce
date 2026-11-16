<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keranjang;
use App\Models\Produk;
use Illuminate\Support\Facades\Auth;

class KeranjangController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'redirect' => route('login')], 401);
        }
        $keranjang = Keranjang::with('produk')->where('user_id', Auth::id())->get();
        return response()->json($keranjang);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'redirect' => route('login')], 401);
        }

        $request->validate([
            'produk_id' => 'required|exists:produks,id',
            'jumlah' => 'required|integer|min:1',
            'opsi' => 'nullable|array',
            // total_harga dari browser dipercaya tidak: hitung ulang dari database.
            'total_harga' => 'nullable|integer|min:0'
        ]);

        $produk = Produk::where('is_active', true)->where('harga', '>', 0)->find($request->produk_id);
        if (!$produk) {
            return response()->json(['error' => 'Produk tidak tersedia untuk dipesan'], 422);
        }

        // Total dihitung dari harga server + biaya opsi, bukan dari browser
        // (antisipasi price tampering lewat payload).
        $opsi = is_array($request->opsi) ? $request->opsi : [];
        $unitPrice = \App\Support\ProdukOptions::unitPrice($produk, $opsi);

        if ($unitPrice === null) {
            return response()->json(['error' => 'Opsi produk tidak valid'], 422);
        }

        $serverTotal = $unitPrice * (int) $request->jumlah;

        // Cari apakah produk dengan opsi yang sama sudah ada di keranjang
        $keranjang = Keranjang::where('user_id', Auth::id())
            ->where('produk_id', $request->produk_id)
            // Karena MySQL json bisa rumit, untuk simplifikasi kita bisa abaikan whereJsonContains atau serialize, 
            // tapi yang paling aman fetch berdasarkan produk_id lalu filter by opsi di PHP atau asumsikan opsi sama.
            ->get()
            ->first(function($item) use ($request) {
                return $item->opsi == $request->opsi;
            });

        if ($keranjang) {
            $keranjang->jumlah += $request->jumlah;
            $keranjang->total_harga += $serverTotal;
            $keranjang->save();
        } else {
            $keranjang = Keranjang::create([
                'user_id' => Auth::id(),
                'produk_id' => $request->produk_id,
                'jumlah' => $request->jumlah,
                'opsi' => $request->opsi,
                'total_harga' => $serverTotal
            ]);
        }

        return response()->json(['success' => true, 'data' => $keranjang]);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'redirect' => route('login')], 401);
        }

        $request->validate([
            'jumlah' => 'required|integer|min:1',
        ]);

        $keranjang = Keranjang::with('produk')->where('user_id', Auth::id())->findOrFail($id);

        // Harga dihitung server supaya total tidak bisa dimanipulasi dari browser.
        $produk = $keranjang->produk;
        $opsi = is_array($keranjang->opsi) ? $keranjang->opsi : [];
        $unitPrice = $produk
            ? \App\Support\ProdukOptions::unitPrice($produk, $opsi)
            : 0;

        if ($unitPrice === null) {
            // Opsi tak dikenal/legacy → pakai harga dasar agar tidak jadi 0.
            $unitPrice = (int) ($produk->harga ?? 0);
        }

        $keranjang->jumlah = $request->jumlah;
        $keranjang->total_harga = (int) $unitPrice * (int) $request->jumlah;
        $keranjang->save();

        return response()->json(['success' => true, 'data' => $keranjang]);
    }

    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'redirect' => route('login')], 401);
        }

        $keranjang = Keranjang::where('user_id', Auth::id())->findOrFail($id);
        $keranjang->delete();

        return response()->json(['success' => true]);
    }

    public function clear()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'redirect' => route('login')], 401);
        }

        Keranjang::where('user_id', Auth::id())->delete();
        return response()->json(['success' => true]);
    }
}
