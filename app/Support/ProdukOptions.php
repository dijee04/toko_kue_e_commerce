<?php

namespace App\Support;

use App\Models\Produk;

/**
 * Sumber tunggal aturan opsi produk (label + biaya tambahan).
 * Dipakai oleh view dan checkout agar harga yang dibayar selalu
 * dihitung dari aturan server, bukan dari payload browser.
 */
class ProdukOptions
{
    public static function isCake(Produk $produk): bool
    {
        $kategori = strtolower($produk->kategori->nama_kategori ?? '');
        $nama = strtolower($produk->nama_produk ?? '');
        $hay = ' ' . $kategori . ' ' . $nama . ' ';

        if (str_contains($kategori, 'ulang tahun')) {
            return true;
        }

        // Word-boundary: 'cake'/'tart' sebagai kata utuh, agar "cupcake"
        // (yang punya opsi berbeda) tidak ikut dianggap kue ukuran.
        return (bool) preg_match('/\bcake\b/', $hay)
            || (bool) preg_match('/\btart\b/', $hay);
    }

    public static function for(Produk $produk): array
    {
        // Produk dengan varian yang sudah spesifik (mis. "Fudgy Brownies Nutella
        // 20x10") tidak perlu pilihan tambahan palsu.
        if (!($produk->has_options ?? false)) {
            return [];
        }

        if (static::isCake($produk)) {
            return [
                [
                    'key' => 'ukuran',
                    'label' => 'Pilih Ukuran Kue',
                    'type' => 'radio',
                    'choices' => ['Personal Size 10cm (+0k)', 'Medium Size 16cm (+50k)', 'Family Size 22cm (+120k)'],
                ],
                [
                    'key' => 'lilin_kartu',
                    'label' => 'Lilin & Kartu Ucapan',
                    'type' => 'radio',
                    'choices' => ['Tanpa Lilin & Kartu (+0k)', 'Lilin Batang & Kartu (+5k)', 'Lilin Angka & Kartu Ucapan (+10k)'],
                ],
            ];
        }

        return [
            [
                'key' => 'varian',
                'label' => 'Pilih Varian Rasa',
                'type' => 'radio',
                'choices' => ['Original Sweet (+0k)', 'Double Choco Premium (+15k)', 'Matcha Velvet Cream (+20k)'],
            ],
            [
                'key' => 'kemasan',
                'label' => 'Pilih Kemasan',
                'type' => 'radio',
                'choices' => ['Kemasan Box Premium (+0k)', 'Kemasan Hampers Keranjang (+45k)'],
            ],
        ];
    }

    /**
     * Harga satuan final (harga dasar + biaya opsi tervalidasi).
     * Mengembalikan null jika ada pilihan yang tidak dikenal.
     */
    public static function unitPrice(Produk $produk, array $selections): ?int
    {
        $price = (int) $produk->harga;

        foreach (static::for($produk) as $option) {
            $key = $option['key'];

            if (!array_key_exists($key, $selections) || $selections[$key] === null || $selections[$key] === '') {
                continue;
            }

            $choice = $selections[$key];

            if (!is_string($choice) || !in_array($choice, $option['choices'], true)) {
                return null;
            }

            $price += static::priceModifier($choice);
        }

        return $price;
    }

    private static function priceModifier(string $choice): int
    {
        if (preg_match('/\(\+(\d+)k\)/i', $choice, $matches)) {
            return ((int) $matches[1]) * 1000;
        }

        return 0;
    }
}
