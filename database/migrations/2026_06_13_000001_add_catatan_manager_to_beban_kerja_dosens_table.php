<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Review BKD oleh manager: tambah kolom catatan_manager
 * (alasan saat BKD ditolak/diminta revisi). Additive, tabel Kel-1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('beban_kerja_dosens', 'catatan_manager')) {
            Schema::table('beban_kerja_dosens', function (Blueprint $table) {
                $table->text('catatan_manager')->nullable()->after('kesimpulan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('beban_kerja_dosens', 'catatan_manager')) {
            Schema::table('beban_kerja_dosens', function (Blueprint $table) {
                $table->dropColumn('catatan_manager');
            });
        }
    }
};
