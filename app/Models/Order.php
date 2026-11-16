<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'kurir_id', 'nama_pelanggan', 'no_wa', 'alamat', 'latitude', 'longitude', 'ongkir', 'total_harga', 'status', 'metode_pembayaran', 'snap_token', 'midtrans_order_id', 'payment_status', 'bukti_pengiriman'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Status yang boleh dipilih admin dari status saat ini.
     * 'dikirim' dan 'selesai' sengaja KOSONG karena hanya kurir yang
     * boleh memindahkan pesanan ke sana (lewat aksi terima & bukti kirim).
     */
    public const ADMIN_TRANSITIONS = [
        'baru' => ['disiapkan', 'dibatalkan'],
        'disiapkan' => ['menunggu_kurir', 'dibatalkan'],
        'menunggu_kurir' => ['disiapkan', 'dibatalkan'],
        'dikirim' => [],
        'selesai' => [],
        'dibatalkan' => [],
    ];

    public const STATUS_LABELS = [
        'baru' => 'Baru',
        'disiapkan' => 'Disiapkan',
        'menunggu_kurir' => 'Menunggu Kurir',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    /**
     * Daftar status tujuan yang diizinkan untuk admin.
     */
    public function adminAllowedStatuses(): array
    {
        return self::ADMIN_TRANSITIONS[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->adminAllowedStatuses(), true);
    }

    public function isFinalStatus(): bool
    {
        return in_array($this->status, ['selesai', 'dibatalkan'], true);
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kurir()
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Tandai pesanan sudah lunas secara idempoten.
     *
     * - payment_status menjadi 'paid' (hanya sekali; aman dipanggil dari
     *   webhook Midtrans maupun endpoint success-local sekaligus).
     * - Status otomatis 'baru' → 'disiapkan' (automatisasi pembelian).
     * - Angka 'terjual' tiap produk ditambah sesuai jumlah di pesanan.
     */
    public function markPaid(): void
    {
        if ($this->payment_status === 'paid') {
            return;
        }

        DB::transaction(function () {
            $data = ['payment_status' => 'paid'];

            if ($this->status === 'baru') {
                $data['status'] = 'disiapkan';
            }

            $this->update($data);

            foreach ($this->items()->get() as $item) {
                Produk::where('id', $item->produk_id)->increment('terjual', (int) $item->jumlah);
            }
        });
    }
}
