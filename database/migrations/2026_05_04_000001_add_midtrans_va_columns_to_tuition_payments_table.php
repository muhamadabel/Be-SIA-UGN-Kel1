<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom Midtrans VA detail ke tuition_payments.
     * Kolom ini menyimpan hasil response dari Core API / Snap API.
     */
    public function up(): void
    {
        Schema::table('tuition_payments', function (Blueprint $table) {
            // Detail VA dari Midtrans Core API
            $table->string('midtrans_va_number', 50)->nullable()
                ->after('midtrans_payment_type')
                ->comment('Nomor VA dari Midtrans');
            $table->string('midtrans_va_bank', 20)->nullable()
                ->after('midtrans_va_number')
                ->comment('Kode bank VA (bca, bni, bri)');

            // Detail Snap API (fallback)
            $table->string('midtrans_snap_token', 255)->nullable()
                ->after('midtrans_va_bank')
                ->comment('Snap token jika pakai Snap API fallback');
            $table->string('midtrans_snap_url', 500)->nullable()
                ->after('midtrans_snap_token')
                ->comment('Snap redirect URL');

            // Expiry time
            $table->timestamp('midtrans_expiry_time')->nullable()
                ->after('midtrans_snap_url')
                ->comment('Waktu kedaluwarsa transaksi Midtrans');

            // Index untuk lookup VA
            $table->index('midtrans_va_number');
            $table->index('midtrans_va_bank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tuition_payments', function (Blueprint $table) {
            $table->dropIndex(['midtrans_va_number']);
            $table->dropIndex(['midtrans_va_bank']);

            $table->dropColumn([
                'midtrans_va_number',
                'midtrans_va_bank',
                'midtrans_snap_token',
                'midtrans_snap_url',
                'midtrans_expiry_time',
            ]);
        });
    }
};
