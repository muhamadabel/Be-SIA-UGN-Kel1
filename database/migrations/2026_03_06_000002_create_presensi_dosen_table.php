<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('presensi_dosen', function (Blueprint $table) {
            $table->id();

            // FK: Dosen yang melakukan presensi
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // FK: Jadwal perkuliahan terkait (opsional, nullable)
            $table->unsignedBigInteger('id_schedule')->nullable();
            $table->foreign('id_schedule')->references('id_schedule')->on('schedules')->onDelete('set null');

            // FK: Periode akademik
            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')->references('id_academic_period')->on('academic_periods')->onDelete('cascade');

            // FK: Setting kampus acuan GPS (nullable, jika presensi manual/izin)
            $table->unsignedBigInteger('id_setting')->nullable();
            $table->foreign('id_setting')->references('id_setting')->on('campus_settings')->onDelete('set null');

            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();

            // Koordinat GPS saat check-in
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Hasil validasi lokasi terhadap campus_settings
            $table->boolean('is_dalam_radius')->default(false);

            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha'])->default('hadir');
            $table->text('keterangan')->nullable();

            // Validasi oleh manager
            $table->boolean('is_validated')->default(false);
            $table->unsignedBigInteger('id_manager_validator')->nullable();
            $table->foreign('id_manager_validator')->references('id_user_si')->on('users_si')->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            // Index untuk mempercepat query per dosen & tanggal
            $table->index(['id_user_si', 'tanggal']);
            $table->index(['id_academic_period', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_dosen');
    }
};
