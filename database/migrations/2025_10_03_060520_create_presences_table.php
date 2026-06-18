<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Guard: skip jika tabel sudah ada (misalnya akibat crash container
        // antara DDL CREATE TABLE dan INSERT ke tabel migrations di Railway).
        if (Schema::hasTable('presences')) {
            return;
        }

        Schema::create('presences', function (Blueprint $table) {
            $table->id('id_presence');

            // Menunjuk ke "Sesi Pertemuan" yang spesifik, bukan "Template Jadwal"
            $table->unsignedBigInteger('id_schedule');
            $table->foreign('id_schedule')->references('id_schedule')->on('schedules')->onDelete('cascade');

            // Menunjuk ke mahasiswa (users_si)
            $table->unsignedBigInteger('id_student');
            $table->foreign('id_student')->references('id_user_si')->on('users_si')->onDelete('cascade');

            $table->timestamp('time')->nullable();
            $table->timestamps();

            // Mahasiswa hanya bisa absen sekali per Sesi
            $table->unique(['id_schedule', 'id_student']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('presences');
    }
};

