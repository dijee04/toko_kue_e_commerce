<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel `products` adalah sisa skema lama yang tidak dipakai.
     * Aplikasi hanya memakai `produks` (dengan k).
     */
    public function up(): void
    {
        Schema::dropIfExists('products');
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug');
            $table->text('deskripsi');
            $table->integer('harga');
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }
};
