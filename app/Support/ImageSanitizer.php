<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ImageSanitizer
{
    /**
     * Buang UTF-8 BOM (EF BB BF) yang kadang menempel di awal file gambar.
     *
     * Browser menolak men-decode gambar yang diawali BOM, sehingga tampil
     * sebagai gambar rusak walaupun server mengirim status 200 dan ukuran
     * file benar. Gejala ini sulit dilacak karena curl/unduhan tetap "sukses".
     */
    public static function stripBom(?string $path): void
    {
        if (! $path) {
            return;
        }

        $absolute = Storage::disk('public')->path($path);

        if (! is_file($absolute) || filesize($absolute) < 3) {
            return;
        }

        $handle = fopen($absolute, 'rb');
        $head = $handle ? fread($handle, 3) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($head !== "\xEF\xBB\xBF") {
            return;
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return;
        }

        file_put_contents($absolute, substr($contents, 3));
    }

    /**
     * Versi file untuk cache-buster (?v=...).
     *
     * Memakai waktu modifikasi file, bukan updated_at baris database, supaya
     * URL ikut berubah ketika isi file diperbaiki (mis. BOM dibuang) walau
     * data di database tidak berubah. Tanpa ini browser tetap memakai gambar
     * rusak yang sudah tersimpan di cache.
     */
    public static function version(?string $path): int
    {
        if (! $path) {
            return 0;
        }

        $rel = ltrim($path, '/');

        $candidates = [
            Storage::disk('public')->path($rel),
            public_path($rel),
            storage_path('app/public/' . $rel),
            // Hosting bersama: docroot = htdocs, aplikasi Laravel di htdocs/laravel,
            // jadi file publik sebenarnya ada di htdocs/uploads/...
            base_path('../' . $rel),
        ];

        foreach ($candidates as $absolute) {
            if (is_file($absolute)) {
                clearstatcache(true, $absolute);

                return (int) filemtime($absolute);
            }
        }

        return 0;
    }

    /**
     * Kirim file gambar ke browser sambil membuang BOM bila ada.
     *
     * Dipakai route /uploads/{path} dan /storage/{path} supaya file lama yang
     * sudah terlanjur ber-BOM tetap tampil tanpa perlu diunggah ulang.
     */
    public static function respond(string $absolutePath, int $maxAge = 86400)
    {
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            abort(404);
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);

            // Sekalian perbaiki file di disk supaya tidak diproses ulang.
            if (is_writable($absolutePath)) {
                @file_put_contents($absolutePath, $contents);
            }
        }

        // Deteksi tipe dari magic bytes; finfo bisa salah baca bila ada BOM.
        $mime = match (true) {
            str_starts_with($contents, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($contents, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($contents, 'GIF8') => 'image/gif',
            str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP' => 'image/webp',
            default => (mime_content_type($absolutePath) ?: 'application/octet-stream'),
        };

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'public, max-age=' . $maxAge,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}