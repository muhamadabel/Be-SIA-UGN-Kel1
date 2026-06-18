<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [KELOMPOK 1] Penelitian (skema PROPOSAL riset) — sesuai Figma "Ajukan Penelitian Baru".
 * Terpisah dari penelitian_ilmiahs (yang = Publikasi Ilmiah). Tabel baru, isolated Kel-1.
 * Alur status: Pengajuan -> (Aktif | Ditolak | Revisi); Aktif -> Selesai.
 * angka_kredit diisi manager saat validasi (Setujui).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penelitian_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user_si'); // ketua/pengusul
            $table->string('judul');
            $table->text('abstrak')->nullable();
            $table->year('tahun')->nullable();
            $table->string('bidang_penelitian')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->string('file_proposal')->nullable();
            $table->json('anggota')->nullable(); // [{nama, peran}]
            $table->enum('status', ['Pengajuan', 'Aktif', 'Selesai', 'Ditolak', 'Revisi'])->default('Pengajuan');
            $table->decimal('angka_kredit', 6, 2)->nullable();
            $table->text('catatan_validasi')->nullable();
            $table->timestamps();

            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penelitian_proposals');
    }
};
