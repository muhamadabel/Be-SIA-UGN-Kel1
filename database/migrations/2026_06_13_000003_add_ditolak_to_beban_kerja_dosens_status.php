<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [KELOMPOK 1] Tambah status 'ditolak' ke status beban_kerja_dosens.
 * Driver-aware: MySQL = native ENUM; PostgreSQL (Supabase) = CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE beban_kerja_dosens DROP CONSTRAINT IF EXISTS beban_kerja_dosens_status_check');
            DB::statement('ALTER TABLE beban_kerja_dosens ALTER COLUMN status TYPE VARCHAR(20)');
            DB::statement("ALTER TABLE beban_kerja_dosens ALTER COLUMN status SET DEFAULT 'draft'");
            DB::statement("ALTER TABLE beban_kerja_dosens ADD CONSTRAINT beban_kerja_dosens_status_check CHECK (status IN ('draft','diajukan','revisi','disetujui','ditolak'))");
        } else {
            DB::statement("ALTER TABLE beban_kerja_dosens MODIFY status ENUM('draft','diajukan','revisi','disetujui','ditolak') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE beban_kerja_dosens DROP CONSTRAINT IF EXISTS beban_kerja_dosens_status_check');
            DB::statement("ALTER TABLE beban_kerja_dosens ADD CONSTRAINT beban_kerja_dosens_status_check CHECK (status IN ('draft','diajukan','revisi','disetujui'))");
        } else {
            DB::statement("ALTER TABLE beban_kerja_dosens MODIFY status ENUM('draft','diajukan','revisi','disetujui') NOT NULL DEFAULT 'draft'");
        }
    }
};
