<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * [KELOMPOK 1] Tambah status 'Revisi' + kolom catatan_validasi ke penelitian_ilmiahs.
 * Driver-aware: MySQL = native ENUM; PostgreSQL (Supabase) = CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE penelitian_ilmiahs DROP CONSTRAINT IF EXISTS penelitian_ilmiahs_status_validasi_check');
            DB::statement('ALTER TABLE penelitian_ilmiahs ALTER COLUMN status_validasi TYPE VARCHAR(30)');
            DB::statement("ALTER TABLE penelitian_ilmiahs ALTER COLUMN status_validasi SET DEFAULT 'Draft'");
            DB::statement("ALTER TABLE penelitian_ilmiahs ADD CONSTRAINT penelitian_ilmiahs_status_validasi_check CHECK (status_validasi IN ('Draft','Diajukan','Disetujui','Ditolak','Revisi'))");
        } else {
            DB::statement("ALTER TABLE penelitian_ilmiahs MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak','Revisi') NOT NULL DEFAULT 'Draft'");
        }

        if (!Schema::hasColumn('penelitian_ilmiahs', 'catatan_validasi')) {
            Schema::table('penelitian_ilmiahs', function (Blueprint $table) {
                $table->text('catatan_validasi')->nullable()->after('status_validasi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penelitian_ilmiahs', 'catatan_validasi')) {
            Schema::table('penelitian_ilmiahs', function (Blueprint $table) {
                $table->dropColumn('catatan_validasi');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE penelitian_ilmiahs DROP CONSTRAINT IF EXISTS penelitian_ilmiahs_status_validasi_check');
            DB::statement("ALTER TABLE penelitian_ilmiahs ADD CONSTRAINT penelitian_ilmiahs_status_validasi_check CHECK (status_validasi IN ('Draft','Diajukan','Disetujui','Ditolak'))");
        } else {
            DB::statement("ALTER TABLE penelitian_ilmiahs MODIFY status_validasi ENUM('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft'");
        }
    }
};
