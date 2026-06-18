<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom id_subject ke tabel krs.
     *
     * Kolom ini menyimpan referensi langsung ke mata kuliah (denormalisasi dari
     * classes.id_subject) sehingga query SKS tidak perlu join ke tabel classes.
     *
     * Catatan: Migration ini hanya dijalankan pada instalasi yang sudah memiliki
     * tabel krs tanpa kolom id_subject (lingkungan lokal). Pada deployment Railway
     * yang baru, kolom ini sudah ada di migration 000004 create_krs_table.
     */
    public function up(): void
    {
        // Guard: Jika kolom sudah ada (Railway deploy via 000004), lewati
        if (Schema::hasColumn('krs', 'id_subject')) {
            return;
        }

        Schema::table('krs', function (Blueprint $table) {
            $table->unsignedBigInteger('id_subject')
                  ->after('id_class')
                  ->nullable(); // nullable agar data lama tidak error

            $table->foreign('id_subject')
                  ->references('id_subject')
                  ->on('subjects')
                  ->onDelete('cascade');

            // Index tambahan untuk filter KRS berdasarkan mata kuliah
            $table->index(['id_subject', 'status'], 'krs_subject_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('krs', 'id_subject')) {
            return;
        }

        Schema::table('krs', function (Blueprint $table) {
            $table->dropForeign(['id_subject']);
            $table->dropIndex('krs_subject_status_idx');
            $table->dropColumn('id_subject');
        });
    }
};
