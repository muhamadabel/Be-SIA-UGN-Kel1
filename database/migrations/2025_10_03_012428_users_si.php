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
        Schema::create('users_si', function (Blueprint $table) {
            // Kolom ID standar Laravel
            $table->id('id_user_si');

            // Kolom untuk data utama pengguna
            $table->string('name'); // Nama lengkap, contoh: "Hanan Fijananto"
            $table->string('username')->unique(); 
            $table->string('email')->unique(); // Email untuk login, harus unik
            $table->timestamp('email_verified_at')->nullable(); // Untuk fitur verifikasi email
            $table->string('password'); // Password yang sudah di-hash
            $table->unsignedBigInteger('id_program')->nullable();

            $table->foreign('id_program')->references('id_program')->on('programs')->onDelete('set null');

            // Kolom untuk peran (role) pengguna
            $table->enum('role', ['mahasiswa', 'dosen', 'admin', 'manager',])->default('mahasiswa');

            // Kolom status, defaultnya true (aktif) saat registrasi
            $table->boolean('is_active')->default(true);
            
            // Kolom Foreign Key untuk program studi (opsional)
            // $table->foreignId('program_id')->nullable()->constrained('programs'); // Aktifkan jika sudah punya tabel 'programs'

            // Token untuk fitur "Remember Me"
            $table->rememberToken();
            
            // Kolom created_at dan updated_at standar Laravel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_si');
    }
};
