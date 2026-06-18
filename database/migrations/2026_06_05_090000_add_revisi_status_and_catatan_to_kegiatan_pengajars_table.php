<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * [KELOMPOK 1] Lengkapi alur validasi Kegiatan Mengajar:
 * - Tambah status 'Revisi' ke enum status_validasi (untuk "Perlu Revisi").
 * - Tambah kolom catatan_validasi (catatan manager saat minta revisi / menolak).
 *
 * Additive & hanya menyentuh tabel Kel-1. Raw ALTER agar tak butuh doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE kegiatan_pengajars
             MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak','Revisi')
             NOT NULL DEFAULT 'Draft'"
        );

        if (!Schema::hasColumn('kegiatan_pengajars', 'catatan_validasi')) {
            Schema::table('kegiatan_pengajars', function (Blueprint $table) {
                $table->text('catatan_validasi')->nullable()->after('status_validasi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kegiatan_pengajars', 'catatan_validasi')) {
            Schema::table('kegiatan_pengajars', function (Blueprint $table) {
                $table->dropColumn('catatan_validasi');
            });
        }

        DB::statement(
            "ALTER TABLE kegiatan_pengajars
             MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak')
             NOT NULL DEFAULT 'Draft'"
        );
    }
};
