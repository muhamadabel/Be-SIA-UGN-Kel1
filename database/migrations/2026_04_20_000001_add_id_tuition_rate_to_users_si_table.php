<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom id_tuition_rate ke tabel users_si.
     * Setiap mahasiswa memiliki tarif UKT default masing-masing,
     * sehingga saat generate tagihan massal tidak perlu penyesuaian manual.
     */
    public function up(): void
    {
        Schema::table('users_si', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tuition_rate')->nullable()->after('id_program');
            $table->foreign('id_tuition_rate')
                ->references('id_tuition_rate')
                ->on('tuition_rates')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_si', function (Blueprint $table) {
            $table->dropForeign(['id_tuition_rate']);
            $table->dropColumn('id_tuition_rate');
        });
    }
};
