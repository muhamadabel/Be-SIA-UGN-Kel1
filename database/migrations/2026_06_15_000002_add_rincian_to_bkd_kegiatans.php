<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Simpan rincian perhitungan AK per kegiatan BKD sesuai Figma "Review BKD":
 * Jumlah (volume) x AK per satuan = Total (sks_beban yang sudah ada).
 * volume/satuan/ak_per_satuan nullable -> data lama (cuma punya sks_beban) tetap valid,
 * FE menampilkan rincian hanya bila kolom ini terisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bkd_kegiatans', function (Blueprint $table) {
            $table->decimal('volume', 8, 2)->nullable()->after('nama_kegiatan');
            $table->string('satuan')->nullable()->after('volume');
            $table->decimal('ak_per_satuan', 6, 2)->nullable()->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('bkd_kegiatans', function (Blueprint $table) {
            $table->dropColumn(['volume', 'satuan', 'ak_per_satuan']);
        });
    }
};
