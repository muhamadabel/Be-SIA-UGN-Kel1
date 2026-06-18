<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('beban_kerja_dosens', function (Blueprint $table) {
            $table->id();
            
            // PASTIKAN DUA BARIS INI TERTULIS DENGAN BENAR:
            $table->unsignedBigInteger('id_user_si'); 
            $table->unsignedBigInteger('id_academic_period'); 
            
            $table->enum('status', ['draft', 'diajukan', 'revisi', 'disetujui'])->default('draft');
            $table->decimal('total_sks_pendidikan', 5, 2)->default(0);
            $table->decimal('total_sks_penelitian', 5, 2)->default(0);
            $table->decimal('total_sks_pengabdian', 5, 2)->default(0);
            $table->decimal('total_sks_penunjang', 5, 2)->default(0);
            $table->enum('kesimpulan', ['Memenuhi', 'Belum Memenuhi'])->nullable();
            $table->timestamps();

            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            $table->foreign('id_academic_period')->references('id_academic_period')->on('academic_periods')->onDelete('restrict');
            $table->unique(['id_user_si', 'id_academic_period'], 'bkd_dosen_periode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beban_kerja_dosens');
    }
};
