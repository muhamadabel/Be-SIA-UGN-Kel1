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
        Schema::create('kegiatan_pengajars', function (Blueprint $table) {
            $table->id();
            
            // Relasi langsung ke tabel users_si
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            
            $table->string('mata_kuliah');
            $table->string('kode_mk')->nullable();
            $table->integer('sks');
            $table->string('kelas'); // Contoh: TI-A, MI-B
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->string('tahun_ajaran'); // Contoh: 2025/2026
            $table->string('file_bukti')->nullable(); // Upload SK Mengajar / Presensi / Bahan Ajar
            $table->enum('status_validasi', ['Draft', 'Diajukan', 'Disetujui', 'Ditolak'])->default('Draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan_pengajars');
    }
};
