<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * [KELOMPOK 1] Tambah status 'Revisi' + kolom catatan_validasi ke kegiatan_pengajars.
 * Driver-aware: MySQL = native ENUM; PostgreSQL (Supabase) = CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kegiatan_pengajars DROP CONSTRAINT IF EXISTS kegiatan_pengajars_status_validasi_check');
            DB::statement('ALTER TABLE kegiatan_pengajars ALTER COLUMN status_validasi TYPE VARCHAR(30)');
            DB::statement("ALTER TABLE kegiatan_pengajars ALTER COLUMN status_validasi SET DEFAULT 'Draft'");
            DB::statement("ALTER TABLE kegiatan_pengajars ADD CONSTRAINT kegiatan_pengajars_status_validasi_check CHECK (status_validasi IN ('Draft','Diajukan','Disetujui','Ditolak','Revisi'))");
        } else {
            DB::statement("ALTER TABLE kegiatan_pengajars MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak','Revisi') NOT NULL DEFAULT 'Draft'");
        }

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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE kegiatan_pengajars DROP CONSTRAINT IF EXISTS kegiatan_pengajars_status_validasi_check');
            DB::statement("ALTER TABLE kegiatan_pengajars ADD CONSTRAINT kegiatan_pengajars_status_validasi_check CHECK (status_validasi IN ('Draft','Diajukan','Disetujui','Ditolak'))");
        } else {
            DB::statement("ALTER TABLE kegiatan_pengajars MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft'");
        }
    }
};
