<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel krs_session_classes menyimpan daftar kelas yang diizinkan untuk dipilih
     * mahasiswa dalam sebuah sesi KRS.
     *
     * Manager menentukan kelas mana saja (beserta mata kuliahnya) yang dapat dipilih
     * saat membuka atau mengelola sebuah sesi KRS. Ini berfungsi sebagai whitelist
     * sehingga mahasiswa hanya bisa memilih dari kelas-kelas yang sudah ditentukan.
     *
     * Setiap entri mewakili satu pasangan (sesi KRS, kelas) yang menandakan bahwa
     * kelas tersebut tersedia untuk dipilih mahasiswa dalam sesi KRS tersebut.
     */
    public function up(): void
    {
        Schema::create('krs_session_classes', function (Blueprint $table) {
            $table->id('id_krs_session_class');

            // Sesi KRS yang memiliki kelas ini
            $table->unsignedBigInteger('id_krs_session');
            $table->foreign('id_krs_session')
                  ->references('id_krs_session')
                  ->on('krs_sessions')
                  ->onDelete('cascade'); // Hapus semua kelas jika sesi dihapus

            // Mata kuliah dari kelas ini (denormalisasi dari classes.id_subject)
            $table->unsignedBigInteger('id_subject');
            $table->foreign('id_subject')
                  ->references('id_subject')
                  ->on('subjects')
                  ->onDelete('cascade');

            // Kelas yang tersedia di sesi ini
            $table->unsignedBigInteger('id_class');
            $table->foreign('id_class')
                  ->references('id_class')
                  ->on('classes')
                  ->onDelete('cascade');

            $table->timestamps();

            // Satu kelas hanya boleh terdaftar satu kali dalam satu sesi
            $table->unique(['id_krs_session', 'id_class'], 'krs_session_class_unique');

            // Index untuk query kelas per sesi dan filter per mata kuliah
            $table->index(['id_krs_session', 'id_subject'], 'krs_session_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_session_classes');
    }
};
