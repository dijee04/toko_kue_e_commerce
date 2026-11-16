<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ulasan kini terikat ke produk tertentu, bukan hanya ke order.
        if (!Schema::hasColumn('reviews', 'produk_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('produk_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('produks')
                    ->cascadeOnDelete();
            });
        }

        // Backfill data lama: satu ulasan per order dipetakan ke produk
        // pertama pada order tersebut (data lama memang tidak menyimpan produk).
        $reviews = DB::table('reviews')->get();

        foreach ($reviews as $review) {
            $produkId = DB::table('order_items')
                ->where('order_id', $review->order_id)
                ->value('produk_id');

            if ($produkId && $review->produk_id === null) {
                DB::table('reviews')
                    ->where('id', $review->id)
                    ->update(['produk_id' => $produkId]);
            }
        }

        // Tambahkan unique(order_id, produk_id) LEBIH DULU agar index baru
        // bisa menggantikan index lama yang dipakai foreign key order_id.
        if (!$this->indexExists('reviews', 'reviews_order_id_produk_id_unique')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->unique(['order_id', 'produk_id']);
            });
        }

        if ($this->indexExists('reviews', 'reviews_order_id_unique')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropUnique(['order_id']);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('reviews', 'reviews_order_id_produk_id_unique')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropUnique(['order_id', 'produk_id']);
            });
        }

        // Kembali ke satu ulasan per order: hapus duplikat, sisakan yang paling awal.
        $duplicateOrderIds = DB::table('reviews')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('order_id');

        foreach ($duplicateOrderIds as $orderId) {
            $keepId = DB::table('reviews')
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->value('id');

            DB::table('reviews')
                ->where('order_id', $orderId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        if (Schema::hasColumn('reviews', 'produk_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropForeign(['produk_id']);
                $table->dropColumn('produk_id');
            });
        }

        if (!$this->indexExists('reviews', 'reviews_order_id_unique')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->unique('order_id');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }
};
