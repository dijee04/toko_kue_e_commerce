<?php

namespace App\Support;

/**
 * Pemusatan resolusi URL gambar produk.
 *
 * Kolom `produks.gambar` menyimpan data dalam beberapa format berbeda:
 *  - "assets/img_produk/Cookies/Nastar_Cookies.png" -> path lengkap relatif public/
 *  - "Kue/chocolate_cake.png", "Cookies/..."        -> relatif ke public/assets/img_produk/
 *  - "produk/xxxx.jpg"                              -> file upload di storage/app/public/
 *
 * Dulu setiap view menebak sendiri-sendiri (dan view admin salah menebak),
 * sehingga sebagian thumbnail patah. Semua view kini memakai helper ini.
 */
class ProdukImage
{
    /**
     * URL gambar produk yang siap dipakai di <img src="...">.
     * Mengembalikan placeholder SVG bila file tidak ditemukan,
     * supaya tidak ada lagi ikon gambar rusak di halaman.
     */
    public static function url(?string $gambar): string
    {
        $gambar = trim((string) $gambar);

        if ($gambar === '') {
            return self::placeholder();
        }

        if (preg_match('#^https?://#i', $gambar)) {
            return $gambar;
        }

        foreach (self::candidates($gambar) as $absolutePath) {
            if (is_file($absolutePath)) {
                return self::toUrl($absolutePath);
            }
        }

        return self::placeholder();
    }

    /**
     * Daftar kemungkinan lokasi file (absolut) untuk satu nilai `gambar`.
     *
     * @return array<int, string>
     */
    public static function candidates(string $gambar): array
    {
        $clean = ltrim(str_replace('\\', '/', $gambar), '/');

        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }

        $candidates = [];

        // Path lengkap relatif public/, mis: assets/img_produk/Cookies/x.png
        if (str_starts_with($clean, 'assets/')) {
            $candidates[] = public_path($clean);
        }

        // Relatif terhadap folder gambar produk, mis: Kue/x.png atau Cookies/x.png
        $candidates[] = public_path('assets/img_produk/' . $clean);

        // Sebagian data lama menyimpan prefix img_produk/
        if (str_starts_with($clean, 'img_produk/')) {
            $candidates[] = public_path('assets/' . $clean);
        }

        if (!str_starts_with($clean, 'assets/')) {
            // File upload: public/storage/... (symlink) atau storage/app/public/...
            $candidates[] = public_path('storage/' . $clean);
            $candidates[] = storage_path('app/public/' . $clean);
        }

        return array_values(array_unique($candidates));
    }

    private static function toUrl(string $absolutePath): string
    {
        $path = str_replace('\\', '/', $absolutePath);
        $publicPath = rtrim(str_replace('\\', '/', public_path()), '/');
        $storagePath = rtrim(str_replace('\\', '/', storage_path('app/public')), '/');

        // Cache-buster dari waktu modifikasi file: saat gambar diperbaiki
        // (mis. file yang tadinya 404 lalu di-upload), URL ikut berubah
        // sehingga browser tidak memakai salinan rusak dari cache.
        clearstatcache(true, $absolutePath);
        $version = (int) @filemtime($absolutePath);
        $suffix = $version > 0 ? '?v=' . $version : '';

        if (str_starts_with($path, $storagePath . '/')) {
            // Public/storage pada hosting gratis tidak punya symlink;
            // route GET /storage/{path} melayani file ini.
            return asset(self::encodePath(substr($path, strlen($storagePath) + 1))) . $suffix;
        }

        if (str_starts_with($path, $publicPath . '/')) {
            return asset(self::encodePath(substr($path, strlen($publicPath) + 1))) . $suffix;
        }

        return asset('storage/' . self::encodePath(basename($path))) . $suffix;
    }

    /**
     * Encode spasi & karakter non-aman per segmen path,
     * mis. "Whole Cakes/Coklat_Cake.png" -> "Whole%20Cakes/Coklat_Cake.png".
     */
    private static function encodePath(string $path): string
    {
        $segments = array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', $path)
        );

        return implode('/', $segments);
    }

    /**
     * Placeholder SVG (data URI) agar tidak perlu file tambahan.
     */
    public static function placeholder(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">'
            . '<rect width="240" height="240" fill="#fdeef3"/>'
            . '<circle cx="120" cy="104" r="34" fill="none" stroke="#e3aebf" stroke-width="8"/>'
            . '<path d="M60 186h120" stroke="#e3aebf" stroke-width="8" stroke-linecap="round"/>'
            . '<text x="120" y="220" font-family="sans-serif" font-size="18" fill="#c48b9e" text-anchor="middle">Dear Seana</text>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}