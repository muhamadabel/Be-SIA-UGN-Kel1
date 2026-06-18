<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Lengkapi penelitian_proposals sesuai Figma "Review Penelitian":
 * - lembaga_dana, jumlah_dana, periode (tanggal_mulai/selesai)
 * - file_laporan (Laporan Akhir, diunggah saat Selesai)
 * - luaran (Luaran Publikasi: {nama, tahun, peringkat, jenis, doi})
 * Semua nullable -> aman untuk data lama, tidak menyentuh tabel kelompok lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penelitian_proposals', function (Blueprint $table) {
            $table->string('lembaga_dana')->nullable()->after('sumber_dana');
            $table->unsignedBigInteger('jumlah_dana')->nullable()->after('lembaga_dana');
            $table->date('tanggal_mulai')->nullable()->after('jumlah_dana');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
            $table->string('file_laporan')->nullable()->after('file_proposal');
            $table->json('luaran')->nullable()->after('anggota');
        });
    }

    public function down(): void
    {
        Schema::table('penelitian_proposals', function (Blueprint $table) {
            $table->dropColumn(['lembaga_dana', 'jumlah_dana', 'tanggal_mulai', 'tanggal_selesai', 'file_laporan', 'luaran']);
        });
    }
};
