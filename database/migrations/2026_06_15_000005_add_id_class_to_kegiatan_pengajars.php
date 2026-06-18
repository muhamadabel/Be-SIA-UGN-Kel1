<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Kegiatan Mengajar sekarang AUTO dari kelas yang diajar dosen (sesuai Figma — tidak ada
 * tambah manual). kegiatan_pengajars jadi record PENGAJUAN klaim AK untuk pasangan (dosen, kelas).
 * id_class menautkan submission ke kelas. Nullable supaya data lama (manual) tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_pengajars', function (Blueprint $table) {
            $table->unsignedBigInteger('id_class')->nullable()->after('id_user_si');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_pengajars', function (Blueprint $table) {
            $table->dropColumn('id_class');
        });
    }
};
