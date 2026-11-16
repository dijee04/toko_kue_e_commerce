<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index untuk mempercepat query daftar pesanan admin / user / kurir.
        if (!Schema::hasIndex('orders', ['user_id'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('user_id', 'orders_user_id_index');
            });
        }
        if (!Schema::hasIndex('orders', ['status'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('status', 'orders_status_index');
            });
        }
        if (!Schema::hasIndex('orders', ['kurir_id'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('kurir_id', 'orders_kurir_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['kurir_id']);
        });
    }
};
