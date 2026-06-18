<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel krs_sessions menyimpan sesi pendaftaran KRS yang dibuka oleh manager.
     *
     * Flow:
     *  1. Manager membuka sesi KRS (status = open) → mahasiswa dapat mendaftar.
     *  2. Manager menutup sesi KRS (status = closed) → pendaftaran tidak dapat dilakukan.
     *  3. Manager mereview dan approve/reject setiap entri KRS.
     *
     * Setiap periode akademik dapat memiliki satu sesi KRS aktif (status = open).
     * Sesi yang sudah ditutup tidak dapat dibuka kembali.
     */
    public function up(): void
    {
        Schema::create('krs_sessions', function (Blueprint $table) {
            $table->id('id_krs_session');

            // Periode akademik yang terkait dengan sesi KRS ini
            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')
                  ->references('id_academic_period')
                  ->on('academic_periods')
                  ->onDelete('cascade');

            // Status sesi: open = mahasiswa dapat mendaftar, closed = pendaftaran ditutup
            $table->enum('status', ['open', 'closed'])->default('open');

            // Catatan dari manager (opsional, misalnya instruksi khusus)
            $table->text('notes')->nullable();

            // Manager/admin yang membuka sesi ini
            $table->unsignedBigInteger('opened_by');
            $table->foreign('opened_by')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('cascade');

            // Waktu sesi dibuka (default = waktu pembuatan record)
            $table->timestamp('opened_at')->useCurrent();

            // Manager/admin yang menutup sesi (nullable, diisi saat sesi ditutup)
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('set null');

            // Waktu sesi ditutup
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Indeks untuk query sesi aktif berdasarkan periode
            $table->index(['id_academic_period', 'status'], 'krs_sessions_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_sessions');
    }
};
