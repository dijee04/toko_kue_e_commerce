<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            // Produk nonaktif tidak tampil di toko, tetapi riwayat pesanan tetap utuh.
            $table->boolean('is_active')->default(true)->after('is_promo');
            // Opsi varian (ukuran/rasa) hanya untuk produk yang memang punya pilihan.
            $table->boolean('has_options')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'has_options']);
        });
    }
};