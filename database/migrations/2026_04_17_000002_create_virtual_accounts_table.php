<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel virtual account — setiap mahasiswa memiliki 1 VA unik.
     * Format VA: prefix bank (4 digit) + NIM mahasiswa.
     */
    public function up(): void
    {
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id('id_virtual_account');

            // FK ke user (mahasiswa), unique = one-to-one
            $table->unsignedBigInteger('id_user_si')->unique();
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Nomor VA unik
            $table->string('va_number', 30)->unique()->comment('Prefix bank + NIM');

            // Info bank
            $table->string('bank_code', 10)->comment('Contoh: BNI, BRI, MANDIRI');
            $table->string('bank_name', 100)->comment('Nama lengkap bank');

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index
            $table->index('bank_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
