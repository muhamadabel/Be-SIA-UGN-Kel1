<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * C.1 - Rekap bulanan kehadiran dosen (sumber kalkulasi potongan gaji)
     */
    public function up(): void
    {
        Schema::create('rekap_presensi_dosen', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')
                ->references('id_user_si')->on('users_si')
                ->onDelete('cascade');

            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')
                ->references('id_academic_period')->on('academic_periods')
                ->onDelete('restrict');

            $table->tinyInteger('bulan')->unsigned()
                ->comment('1-12');
            $table->smallInteger('tahun')->unsigned();

            $table->integer('total_hadir')->default(0);
            $table->integer('total_izin')->default(0);
            $table->integer('total_sakit')->default(0);
            $table->integer('total_alpha')->default(0);
            $table->integer('total_hari_kerja')->default(0)
                ->comment('Jumlah hari unik dengan catatan presensi');

            $table->timestamps();

            // Satu rekap per dosen per bulan per tahun
            $table->unique(['id_user_si', 'bulan', 'tahun'], 'rekap_dosen_bulan_tahun_unique');

            $table->index(['id_user_si', 'id_academic_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekap_presensi_dosen');
    }
};
