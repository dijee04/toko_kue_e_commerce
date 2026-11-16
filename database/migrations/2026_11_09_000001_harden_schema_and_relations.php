<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Simpan ID transaksi Midtrans agar callback bisa dicocokkan persis.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->unique()->after('snap_token');
        });

        // Kolom profil kurir yang sebelumnya dibuang diam-diam.
        Schema::table('users', function (Blueprint $table) {
            $table->string('kendaraan')->nullable()->after('alamat');
            $table->string('plat_nomor')->nullable()->after('kendaraan');
        });

        // Satu order hanya boleh punya satu ulasan.
        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('order_id');
        });

        // Cegah riwayat pesanan ikut terhapus saat produk/kategori dihapus.
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('produk_id')->references('id')->on('produks')->restrictOnDelete();
        });

        Schema::table('produks', function (Blueprint $table) {
            $table->dropForeign(['kategori_id']);
        });
        Schema::table('produks', function (Blueprint $table) {
            $table->foreign('kategori_id')->references('id')->on('kategoris')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropForeign(['kategori_id']);
        });
        Schema::table('produks', function (Blueprint $table) {
            $table->foreign('kategori_id')->references('id')->on('kategoris')->cascadeOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('produk_id')->references('id')->on('produks')->cascadeOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kendaraan', 'plat_nomor']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn('midtrans_order_id');
        });
    }
};
