<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel tarif UKT berjenjang per program studi.
     * Admin dapat mengatur group UKT (UKT 1..8) dengan nominal berbeda per prodi.
     */
    public function up(): void
    {
        Schema::create('tuition_rates', function (Blueprint $table) {
            $table->id('id_tuition_rate');

            // FK ke program studi
            $table->unsignedBigInteger('id_program');
            $table->foreign('id_program')->references('id_program')->on('programs')->onDelete('cascade');

            // Nama group UKT (misal: "UKT 1", "UKT 2", ... "UKT 8")
            $table->string('group_name', 50)->comment('Contoh: UKT 1, UKT 2, ..., UKT 8');

            // Nominal UKT untuk group ini
            $table->decimal('amount', 15, 2)->comment('Nominal UKT dalam Rupiah');

            // Status aktif
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Satu prodi hanya boleh punya 1 group_name unik
            $table->unique(['id_program', 'group_name']);

            // Index untuk query tarif aktif
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_rates');
    }
};
