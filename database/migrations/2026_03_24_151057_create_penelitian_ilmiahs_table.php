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
        Schema::create('penelitian_ilmiahs', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->enum('jenis_output', ['Jurnal Nasional', 'Jurnal Internasional', 'Prosiding', 'Buku', 'Paten']);
            $table->string('nama_publikasi');
            $table->year('tahun_terbit');
            $table->string('volume')->nullable();
            $table->string('nomor')->nullable();
            $table->string('halaman')->nullable();
            $table->string('penerbit')->nullable();
            $table->string('doi_url')->nullable();
            $table->string('status_akreditasi')->nullable();
            $table->string('file_artikel')->nullable();
            $table->enum('status_validasi', ['Draft', 'Diajukan', 'Disetujui', 'Ditolak'])->default('Draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penelitian_ilmiahs');
    }
};
