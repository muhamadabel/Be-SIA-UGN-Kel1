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
        Schema::create('pengajuan_kenaikan_jabatans', function (Blueprint $table) {
            $table->id('id_pengajuan');
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            $table->string('jabatan_sekarang')->default('Tenaga Pengajar');
            $table->string('jabatan_tujuan')->default('Asisten Ahli');
            $table->decimal('total_kum', 8, 2)->default(0);
            $table->enum('status', ['eligible', 'diajukan', 'divalidasi_manager', 'ditolak', 'disetujui_pak'])->default('eligible');
            $table->text('catatan_manager')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_kenaikan_jabatans');
    }
};
