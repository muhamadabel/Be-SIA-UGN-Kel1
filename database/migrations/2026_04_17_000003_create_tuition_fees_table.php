<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel tagihan UKT per mahasiswa per semester.
     * 1 mahasiswa hanya boleh punya 1 tagihan per periode akademik.
     */
    public function up(): void
    {
        Schema::create('tuition_fees', function (Blueprint $table) {
            $table->id('id_tuition_fee');

            // FK ke mahasiswa
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // FK ke periode akademik (semester)
            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')->references('id_academic_period')->on('academic_periods')->onDelete('cascade');

            // FK ke tarif UKT (nullable — bisa diisi manual tanpa acuan tarif)
            $table->unsignedBigInteger('id_tuition_rate')->nullable();
            $table->foreign('id_tuition_rate')->references('id_tuition_rate')->on('tuition_rates')->onDelete('set null');

            // Nominal
            $table->decimal('amount', 15, 2)->comment('Nominal UKT bruto');
            $table->decimal('discount', 15, 2)->default(0)->comment('Potongan beasiswa/diskon');
            $table->decimal('final_amount', 15, 2)->comment('amount - discount = yang harus dibayar');

            // Status tagihan
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])->default('unpaid');

            // Batas pembayaran
            $table->date('due_date')->nullable()->comment('Tanggal jatuh tempo');

            // Catatan admin
            $table->text('notes')->nullable();

            $table->timestamps();

            // 1 mahasiswa hanya 1 tagihan per semester
            $table->unique(['id_user_si', 'id_academic_period']);

            // Index untuk query filtering
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_fees');
    }
};
