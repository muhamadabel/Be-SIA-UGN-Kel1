<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel krs_quotas menyimpan kuota maksimum SKS yang dialokasikan
     * kepada setiap mahasiswa untuk satu periode akademik tertentu.
     * Kuota ini diatur oleh admin atau manajer.
     */
    public function up(): void
    {
        Schema::create('krs_quotas', function (Blueprint $table) {
            $table->id('id_krs_quota');

            // Mahasiswa yang mendapatkan kuota ini
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('cascade');

            // Periode akademik tempat kuota ini berlaku
            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')
                  ->references('id_academic_period')
                  ->on('academic_periods')
                  ->onDelete('cascade');

            // Maksimum SKS yang boleh diambil oleh mahasiswa
            $table->unsignedTinyInteger('max_sks')->default(24);

            // Catatan opsional dari admin/manager
            $table->text('notes')->nullable();

            // Admin atau manager yang menetapkan kuota ini
            $table->unsignedBigInteger('set_by');
            $table->foreign('set_by')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('cascade');

            $table->timestamps();

            // Satu mahasiswa hanya bisa memiliki satu kuota per periode akademik
            $table->unique(['id_user_si', 'id_academic_period'], 'krs_quotas_student_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_quotas');
    }
};
