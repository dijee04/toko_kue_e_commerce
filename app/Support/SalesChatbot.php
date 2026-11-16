<?php

namespace App\Support;

use App\Models\Produk;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Mesin chatbot penjualan berbasis aturan (rule-based).
 *
 * Tugasnya melayani calon pembeli 24/7: menjawab pertanyaan produk & harga,
 * memberi rekomendasi (termasuk berdasarkan budget), info stok, ongkir,
 * pembayaran, dan cara memesan. Tidak memanggil layanan AI eksternal sehingga
 * tetap jalan tanpa API key dan tanpa biaya.
 */
class SalesChatbot
{
    /** @var Collection<int, Produk>|null */
    private $produks = null;

    /**
     * @return array{reply: string, quick_replies: array<int, string>}
     */
    public function reply(string $message): array
    {
        $text = $this->normalize($message);

        if ($text === '') {
            return $this->msg(
                "Halo! 👋 Saya Seana Assistant. Ada yang bisa saya bantu?",
                $this->defaultChips()
            );
        }

        // 1) Niat yang jelas (dicek sebelum pencocokan produk agar
        //    "stok brownies" tidak berhenti di daftar produk).
        if ($this->hasAny($text, ['ongkir', 'ongkos kirim', 'kirim', 'pengiriman', 'antar', 'dikirim', 'kurir', 'jarak', 'alamat toko', 'lokasi toko', 'dimana toko'])) {
            return $this->shippingAnswer();
        }

        if ($this->hasAny($text, ['bayar', 'pembayaran', 'transfer', 'qris', 'gopay', 'ovo', 'dana', 'shopeepay', 'virtual account', 'kartu', 'cod', 'midtrans', 'rekening'])) {
            return $this->paymentAnswer();
        }

        if ($this->hasAny($text, ['cara pesan', 'cara order', 'cara beli', 'gimana pesan', 'bagaimana pesan', 'checkout', 'pemesanan', 'minimal order', 'minimal pembelian'])) {
            return $this->orderAnswer();
        }

        if ($this->hasAny($text, ['stok', 'stock', 'ready', 'tersedia', 'sisa', 'kosong', 'habis', 'preorder', 'pre order'])) {
            return $this->stockAnswer();
        }

        if ($this->hasAny($text, ['promo', 'diskon', 'voucher', 'potongan harga', 'murah', 'sale'])) {
            return $this->promoAnswer();
        }

        if ($this->hasAny($text, ['custom', 'desain', 'birthday', 'wedding', 'nikahan', 'ulang tahun'])) {
            return $this->customAnswer();
        }

        if ($this->hasAny($text, ['jam buka', 'buka jam', 'tutup jam', 'jam operasional', 'operasional', 'buka kapan', 'tutup kapan', '24 jam'])) {
            return $this->hoursAnswer();
        }

        if ($this->hasAny($text, ['kontak', 'hubungi', 'whatsapp', 'telepon', 'telpon', 'instagram', 'email', 'admin'])) {
            return $this->contactAnswer();
        }

        if ($this->hasAny($text, ['menu', 'katalog', 'daftar produk', 'produk apa', 'jual apa', 'apa saja', 'pilihan'])) {
            return $this->catalogAnswer();
        }

        // 2) Rekomendasi berdasarkan budget lebih dulu, lalu rekomendasi umum.
        $budget = $this->budgetFrom($text);
        if ($budget !== null) {
            return $this->budgetAnswer($budget);
        }

        if ($this->hasAny($text, ['rekomendasi', 'recommend', 'saran', 'favorit', 'best seller', 'terlaris', 'paling laris', 'bingung', 'pilih apa'])) {
            return $this->recommendAnswer();
        }

        // 3) Sebutan produk spesifik.
        $matches = $this->matchProducts($text);
        if ($matches->isNotEmpty()) {
            $top = $matches->first()['produk'];
            $topScore = $matches->first()['score'];
            $secondScore = $matches->count() > 1 ? $matches[1]['score'] : 0;

            if ($matches->count() === 1 || $topScore >= $secondScore + 3) {
                return $this->productAnswer($top);
            }

            return $this->productListAnswer(
                $matches->take(5)->pluck('produk')->all(),
                'Ini produk yang paling cocok dengan pencarianmu:'
            );
        }

        // 4) Sebutan kategori (mis. "cookies", "cheesecake").
        $kategori = $this->categoryFrom($text);
        if ($kategori !== null) {
            return $this->categoryAnswer($kategori);
        }

        // 5) Sapaan / terima kasih / pamit (hanya untuk pesan pendek).
        if (mb_strlen($text) <= 40) {
            if ($this->hasAny($text, ['makasih', 'terima kasih', 'thanks', 'thank you', 'tq', 'thx'])) {
                return $this->msg(
                    "Sama-sama! 😊 Kalau butuh rekomendasi kue atau info ongkir, tinggal tanya ya.",
                    ['Lihat menu', 'Rekomendasi', 'Cara pesan']
                );
            }

            if ($this->hasAny($text, ['bye', 'dadah', 'sampai jumpa', 'selamat tinggal'])) {
                return $this->msg(
                    "Terima kasih sudah mampir! Semoga harimu manis seperti kue kami 🍰",
                    ['Lihat menu', 'Hubungi admin']
                );
            }

            if ($this->hasAny($text, ['halo', 'hallo', 'hai', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'assalam', 'permisi', 'min', 'kak'])) {
                return $this->msg(
                    "Halo! 👋 Saya Seana Assistant, siap bantu 24/7.\n\nSaya bisa bantu: cek harga & stok, rekomendasi kue, info ongkir, dan cara bayar.",
                    $this->defaultChips()
                );
            }
        }

        return $this->fallbackAnswer();
    }

    // ---------------------------------------------------------------------
    // Jawaban
    // ---------------------------------------------------------------------

    private function productAnswer(Produk $produk): array
    {
        $harga = $this->rupiah((int) $produk->harga);
        $link = route('menu.show', $produk->id);

        $reply = "🍰 {$produk->nama_produk}\nHarga: {$harga}\n";
        if ($produk->deskripsi) {
            $reply .= "\n{$produk->deskripsi}\n";
        }
        $reply .= "\nStatus: tersedia — dibuat fresh setelah pesanan masuk.";
        $reply .= "\nDetail & pesan: {$link}";

        return $this->msg($reply, ['Lihat menu', 'Ongkir', 'Cara pesan']);
    }

    /**
     * @param  array<int, Produk>  $produks
     */
    private function productListAnswer(array $produks, string $intro): array
    {
        $lines = [$intro];
        foreach ($produks as $p) {
            $lines[] = '• ' . $p->nama_produk . ' — ' . $this->rupiah((int) $p->harga);
        }
        $lines[] = "\nKlik produk di halaman Menu untuk detail dan pemesanan.";

        return $this->msg(implode("\n", $lines), ['Lihat menu', 'Rekomendasi', 'Ongkir']);
    }

    private function recommendAnswer(): array
    {
        $semua = $this->produks()->sortByDesc('terjual');

        $pilihan = collect();
        foreach ($semua->groupBy(fn (Produk $p) => optional($p->kategori)->nama_kategori ?? 'Lainnya') as $items) {
            $pilihan->push($items->first());
        }

        if ($pilihan->count() < 4) {
            $pilihan = $semua;
        }

        return $this->productListAnswer(
            $pilihan->take(5)->values()->all(),
            'Ini rekomendasi favorit dari kami:'
        );
    }

    private function budgetAnswer(int $budget): array
    {
        $produks = $this->produks()
            ->filter(fn (Produk $p) => (int) $p->harga <= $budget)
            ->sortByDesc('terjual')
            ->take(5)
            ->values();

        if ($produks->isEmpty()) {
            return $this->msg(
                "Untuk budget {$this->rupiah($budget)}, belum ada produk yang pas. Produk termurah kami mulai "
                . $this->rupiah((int) $this->produks()->min('harga')) . ".\nMau lihat semua menu?",
                ['Lihat menu', 'Rekomendasi', 'Hubungi admin']
            );
        }

        return $this->productListAnswer(
            $produks->all(),
            "Dengan budget {$this->rupiah($budget)}, ini rekomendasinya:"
        );
    }

    private function categoryAnswer(string $kategori): array
    {
        $produks = $this->produks()
            ->filter(function (Produk $p) use ($kategori) {
                return mb_strtolower((string) optional($p->kategori)->nama_kategori) === mb_strtolower($kategori);
            })
            ->sortBy('harga')
            ->values();

        if ($produks->isEmpty()) {
            return $this->catalogAnswer();
        }

        return $this->productListAnswer(
            $produks->take(6)->all(),
            "Kategori {$kategori} ({$produks->count()} produk):"
        );
    }

    private function catalogAnswer(): array
    {
        $grouped = $this->produks()
            ->groupBy(fn (Produk $p) => optional($p->kategori)->nama_kategori ?? 'Lainnya')
            ->sortByDesc(fn (Collection $items) => $items->count());

        $lines = ['Ini kategori yang tersedia:'];
        foreach ($grouped as $nama => $items) {
            $min = (int) $items->min('harga');
            $max = (int) $items->max('harga');
            $harga = $min === $max
                ? $this->rupiah($min)
                : $this->rupiah($min) . ' – ' . $this->rupiah($max);
            $lines[] = "• {$nama} ({$items->count()} produk) — {$harga}";
        }
        $lines[] = "\nLihat semuanya di halaman Menu: " . route('menu');

        return $this->msg(implode("\n", $lines), ['Rekomendasi', 'Ongkir', 'Cara pesan']);
    }

    private function shippingAnswer(): array
    {
        return $this->msg(
            "Ongkir dihitung otomatis dari titik toko ke alamatmu:\n"
            . "• Rp 2.000 per km, minimal Rp 5.000\n"
            . "• Titik antar dipilih lewat peta saat checkout, ongkir langsung muncul\n"
            . "• Area layanan utama: Jakarta & sekitarnya\n\n"
            . "Untuk pengiriman luar kota atau jumlah besar, silakan hubungi admin.",
            ['Cara pesan', 'Bayar', 'Hubungi admin']
        );
    }

    private function paymentAnswer(): array
    {
        return $this->msg(
            "Pembayaran diproses aman lewat Midtrans:\n"
            . "• QRIS, GoPay, ShopeePay, DANA, OVO\n"
            . "• Virtual Account (BCA, BNI, BRI, Mandiri)\n"
            . "• Kartu kredit/debit\n\n"
            . "Ingin transfer manual? Hubungi admin lewat WhatsApp untuk info rekening.",
            ['Cara pesan', 'Ongkir', 'Hubungi admin']
        );
    }

    private function orderAnswer(): array
    {
        return $this->msg(
            "Cara pesan:\n"
            . "1. Buka halaman Menu, pilih produk.\n"
            . "2. Klik \"Tambah\" (keranjang) atau \"Pesan Sekarang\".\n"
            . "3. Isi nama, nomor WA, alamat, lalu geser pin lokasi di peta.\n"
            . "4. Bayar lewat Midtrans — pesanan langsung diproses.\n\n"
            . "Belum punya akun? Cukup daftar dengan email, atau masuk dengan Google.",
            ['Lihat menu', 'Ongkir', 'Bayar']
        );
    }

    private function stockAnswer(): array
    {
        return $this->msg(
            "Semua menu dibuat fresh setelah pesanan masuk (pre-order), jadi stok selalu tersedia.\n\n"
            . "Khusus custom cake, hampers, dan pesanan dalam jumlah banyak, mohon konfirmasi 2–3 hari sebelumnya.",
            ['Lihat menu', 'Custom cake', 'Hubungi admin']
        );
    }

    private function promoAnswer(): array
    {
        return $this->msg(
            "Promo yang sedang berjalan ditandai badge khusus di halaman Menu.\n\n"
            . "Ingin harga terbaik? Tanyakan paket hampers atau pesanan jumlah banyak ke admin — biasanya ada harga khusus.",
            ['Lihat menu', 'Hampers', 'Hubungi admin']
        );
    }

    private function customAnswer(): array
    {
        return $this->msg(
            "Untuk custom cake & hampers:\n"
            . "• Custom whole cake 16–20 cm mulai Rp 350.000, desain sesuai permintaan\n"
            . "• Hampers mulai Rp 300.000\n"
            . "• Pemesanan minimal 2–3 hari sebelum acara\n\n"
            . "Mau saya arahkan ke admin untuk konsultasi desain?",
            ['Hubungi admin', 'Lihat menu', 'Ongkir']
        );
    }

    private function hoursAnswer(): array
    {
        return $this->msg(
            "Pemesanan online lewat website terbuka 24/7, termasuk chat ini. 🕐\n\n"
            . "Admin membalas dan proses produksi berjalan setiap hari sekitar 08.00–20.00 WIB. "
            . "Pesanan yang masuk di luar jam tersebut diproses pada hari berikutnya.",
            ['Cara pesan', 'Lihat menu', 'Hubungi admin']
        );
    }

    private function contactAnswer(): array
    {
        $setting = Setting::first();
        $wa = preg_replace('/\D/', '', (string) ($setting->wa_number ?? '6285121111397'));

        return $this->msg(
            "Kamu bisa menghubungi admin Dear Seana:\n"
            . "• WhatsApp: https://wa.me/{$wa}\n"
            . "• Instagram: @dearseana\n\n"
            . "Untuk pertanyaan cepat soal menu, harga, atau ongkir, saya siap bantu di sini.",
            ['Lihat menu', 'Ongkir', 'Cara pesan']
        );
    }

    private function fallbackAnswer(): array
    {
        return $this->msg(
            "Maaf, saya belum menangkap maksudnya. 🙏\n\n"
            . "Saya bisa bantu soal: harga produk, rekomendasi kue, stok, ongkir, pembayaran, dan cara pesan.\n"
            . "Coba tulis misalnya: \"harga brownies\" atau \"ongkir ke Jakarta\".",
            $this->defaultChips()
        );
    }

    // ---------------------------------------------------------------------
    // Pencocokan produk & kategori
    // ---------------------------------------------------------------------

    /**
     * @return Collection<int, array{produk: Produk, score: int}>
     */
    private function matchProducts(string $text): Collection
    {
        $stopwords = ['yang', 'dan', 'dengan', 'untuk', 'ukuran', 'harga', 'menu', 'produk', 'kue'];

        return $this->produks()
            ->map(function (Produk $produk) use ($text, $stopwords) {
                $nama = $this->normalize($produk->nama_produk);
                $score = 0;

                if ($nama !== '' && str_contains($text, $nama)) {
                    $score += 5;
                }

                foreach (explode(' ', $nama) as $token) {
                    if (mb_strlen($token) < 4 || in_array($token, $stopwords, true)) {
                        continue;
                    }
                    if (str_contains($text, $token)) {
                        $score += 1;
                    }
                }

                return ['produk' => $produk, 'score' => $score];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    private function categoryFrom(string $text): ?string
    {
        $peta = [
            'Kue Kering' => ['cookies', 'cookie', 'kue kering'],
            'Dessert' => ['dessert', 'brownies', 'brownie', 'basque', 'cheesecake', 'fudgy'],
            'Kue Ulang Tahun' => ['whole cake'],
            'Savoury' => ['savoury', 'savory', 'dimsum', 'gyoza', 'spaghetti', 'mac and cheese', 'mac cheese', 'gurih'],
            'Hampers' => ['hampers', 'parcel', 'bingkisan'],
        ];

        foreach ($peta as $kategori => $kataKunci) {
            if ($this->hasAny($text, $kataKunci)) {
                return $kategori;
            }
        }

        return null;
    }

    private function budgetFrom(string $text): ?int
    {
        $punyaKataBudget = $this->hasAny($text, [
            'budget', 'bajet', 'bujet', 'maksimal', 'max', 'dibawah', 'di bawah',
            'sampai', 'kurang dari', 'rekomendasi', 'cari yang',
        ]);

        if (! $punyaKataBudget) {
            return null;
        }

        // Gabungkan pemisah ribuan yang sudah dinormalisasi jadi spasi ("150 000").
        $rapi = preg_replace('/(\d)[ .](\d{3})(?!\d)/', '$1$2', $text) ?? $text;

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(jt|juta)/', $rapi, $m)) {
            return (int) round(((float) str_replace(',', '.', $m[1])) * 1000000);
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)\b/', $rapi, $m)) {
            return (int) round(((float) str_replace(',', '.', $m[1])) * 1000);
        }
        if (preg_match('/\b(\d{4,})\b/', $rapi, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Util
    // ---------------------------------------------------------------------

    private function produks(): Collection
    {
        if ($this->produks === null) {
            $this->produks = Produk::with('kategori')
                ->where('is_active', true)
                ->where('harga', '>', 0)
                ->get();
        }

        return $this->produks;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function rupiah(int $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    /**
     * @param  array<int, string>  $chips
     * @return array{reply: string, quick_replies: array<int, string>}
     */
    private function msg(string $reply, array $chips = []): array
    {
        return ['reply' => $reply, 'quick_replies' => array_values($chips)];
    }

    /**
     * @return array<int, string>
     */
    private function defaultChips(): array
    {
        return ['Lihat menu', 'Rekomendasi', 'Ongkir', 'Cara pesan'];
    }
}