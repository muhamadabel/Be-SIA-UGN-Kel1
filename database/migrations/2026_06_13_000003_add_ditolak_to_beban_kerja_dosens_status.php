<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [KELOMPOK 1] Review BKD: tambah status 'ditolak' ke enum beban_kerja_dosens
 * (Figma "BKD Ditolak"). 'revisi' tetap ada untuk kompatibilitas data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE beban_kerja_dosens
             MODIFY status ENUM('draft','diajukan','revisi','disetujui','ditolak')
             NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE beban_kerja_dosens
             MODIFY status ENUM('draft','diajukan','revisi','disetujui')
             NOT NULL DEFAULT 'draft'"
        );
    }
};
