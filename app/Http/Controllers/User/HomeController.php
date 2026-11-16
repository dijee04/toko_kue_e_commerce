<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Produk;

class HomeController extends Controller
{
    public function index()
    {
        $produks = Produk::with('kategori')->withCount('reviews')->where('is_active', true)->get();
        return view('user.landing_page.home', compact('produks'));
    }

    public function ourStory()
    {
        return view('user.landing_page.our_story');
    }

    public function menu()
    {
        $produks = Produk::with('kategori')->withCount('reviews')->where('is_active', true)->get();
        return view('user.landing_page.menu', compact('produks'));
    }

    public function show($id)
    {
        $produk = Produk::with('kategori')->where('is_active', true)->findOrFail($id);
        
        // Ulasan sekarang terikat langsung ke produk.
        $reviews = \App\Models\Review::where('produk_id', $produk->id)
            ->with('user')
            ->latest()
            ->get();
        
        // Calculate average rating (0 bila belum ada ulasan — jangan tampilkan angka palsu)
        $averageRating = $reviews->avg('rating') ?? 0;
        $reviewCount = $reviews->count();
        
        // Fetch related products in the same category
        $relatedProduks = Produk::where('kategori_id', $produk->kategori_id)
            ->where('id', '!=', $id)
            ->where('is_active', true)
            ->where('harga', '>', 0)
            ->take(4)
            ->get();
            
        return view('user.landing_page.detail', compact('produk', 'reviews', 'averageRating', 'reviewCount', 'relatedProduks'));
    }

    }
