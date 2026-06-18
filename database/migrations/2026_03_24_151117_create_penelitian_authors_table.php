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
        Schema::create('penelitian_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penelitian_id')->constrained('penelitian_ilmiahs')->onDelete('cascade');
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            $table->enum('peran', ['Penulis Utama', 'Anggota']);
            $table->integer('urutan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penelitian_authors');
    }
};
