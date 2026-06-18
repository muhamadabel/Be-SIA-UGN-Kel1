<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel pembayaran UKT — 1 tagihan = 1 pembayaran (no cicilan).
     * Mendukung upload bukti bayar manual + field untuk integrasi Midtrans di masa depan.
     */
    public function up(): void
    {
        Schema::create('tuition_payments', function (Blueprint $table) {
            $table->id('id_tuition_payment');

            // FK ke tagihan (unique = 1:1, tidak boleh cicilan)
            $table->unsignedBigInteger('id_tuition_fee')->unique();
            $table->foreign('id_tuition_fee')->references('id_tuition_fee')->on('tuition_fees')->onDelete('cascade');

            // FK ke mahasiswa yang membayar
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Nominal yang dibayarkan
            $table->decimal('amount_paid', 15, 2);

            // Metode pembayaran
            $table->enum('payment_method', ['virtual_account', 'bank_transfer', 'manual'])->default('bank_transfer');

            // Bukti pembayaran (path file di local storage)
            $table->string('payment_proof')->nullable()->comment('Path ke file bukti bayar');

            // Referensi transaksi bank
            $table->string('transaction_reference', 100)->nullable()->comment('Nomor referensi dari bank');

            // === Field untuk future Midtrans integration ===
            $table->string('midtrans_transaction_id', 100)->nullable()->comment('Transaction ID dari Midtrans');
            $table->string('midtrans_order_id', 100)->nullable()->comment('Order ID untuk Midtrans');
            $table->string('midtrans_payment_type', 50)->nullable()->comment('Tipe pembayaran Midtrans');
            $table->json('midtrans_response')->nullable()->comment('Raw JSON response dari Midtrans');

            // Status verifikasi
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');

            // Admin yang memverifikasi
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id_user_si')->on('users_si')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();

            // Alasan penolakan
            $table->text('rejection_reason')->nullable();

            // Catatan admin
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            // Index untuk query filtering
            $table->index('verification_status');
            $table->index('payment_method');
            $table->index('midtrans_transaction_id');
            $table->index('midtrans_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_payments');
    }
};
