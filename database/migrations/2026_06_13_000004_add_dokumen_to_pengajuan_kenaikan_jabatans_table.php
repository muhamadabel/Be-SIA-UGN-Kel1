<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Simpan dokumen syarat saat pengajuan kenaikan jabatan
 * (SK Pangkat, SKP, dll — Figma "Upload Dokumen"). JSON {dokumen_1: path, ...}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pengajuan_kenaikan_jabatans', 'dokumen')) {
            Schema::table('pengajuan_kenaikan_jabatans', function (Blueprint $table) {
                $table->json('dokumen')->nullable()->after('catatan_manager');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pengajuan_kenaikan_jabatans', 'dokumen')) {
            Schema::table('pengajuan_kenaikan_jabatans', function (Blueprint $table) {
                $table->dropColumn('dokumen');
            });
        }
    }
};
