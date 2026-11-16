<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_produk',
        'deskripsi',
        'harga',
        'gambar',
        'kategori_id',
        'rating_avg',
        'terjual',
        'is_promo',
        'is_active',
        'has_options',
    ];

    protected $casts = [
        'is_promo' => 'boolean',
        'is_active' => 'boolean',
        'has_options' => 'boolean',
    ];

    /**
     * Produk yang boleh tampil & dipesan di toko.
     */
    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
