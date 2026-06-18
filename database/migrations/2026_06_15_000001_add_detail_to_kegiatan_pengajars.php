<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Lengkapi kegiatan_pengajars sesuai Figma "Review Kegiatan Mengajar":
 * - jumlah_mahasiswa, jenis_kelas (Teori/Praktikum)
 * - Berkas Wajib terpisah: file_sk (SK Mengajar), file_nilai (Bukti Submit Nilai),
 *   file_presensi (Rekap Presensi). file_bukti lama dibiarkan (kompatibel data lama).
 * Semua nullable -> aman untuk data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_pengajars', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_mahasiswa')->nullable()->after('sks');
            $table->string('jenis_kelas')->nullable()->after('kelas'); // Teori | Praktikum
            $table->string('file_sk')->nullable()->after('file_bukti');
            $table->string('file_nilai')->nullable()->after('file_sk');
            $table->string('file_presensi')->nullable()->after('file_nilai');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_pengajars', function (Blueprint $table) {
            $table->dropColumn(['jumlah_mahasiswa', 'jenis_kelas', 'file_sk', 'file_nilai', 'file_presensi']);
        });
    }
};
