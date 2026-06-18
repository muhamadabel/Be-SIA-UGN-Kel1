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
        Schema::create('bkd_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_bkd'); // Relasi ke tabel beban_kerja_dosens
            
            $table->enum('kategori', ['Pendidikan', 'Penelitian', 'Pengabdian', 'Penunjang']);
            $table->string('nama_kegiatan');
            $table->decimal('sks_beban', 5, 2)->default(0); // SKS yang diklaim dosen
            
            // Dokumen pendukung (Path file PDF/Gambar)
            $table->string('bukti_penugasan')->nullable(); 
            $table->string('bukti_kinerja')->nullable();
            
            // Untuk validasi Manager/Asesor nantinya
            $table->decimal('sks_diakui', 5, 2)->default(0); // SKS yang disetujui asesor
            $table->boolean('is_approved')->default(false);
            $table->text('catatan_asesor')->nullable();
            
            $table->timestamps();

            // Kunci relasinya ke tabel header BKD
            $table->foreign('id_bkd')->references('id')->on('beban_kerja_dosens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkd_kegiatans');
    }
};