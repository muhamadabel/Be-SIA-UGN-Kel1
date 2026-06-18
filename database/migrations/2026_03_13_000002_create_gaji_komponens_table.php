<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * C.4 - Detail komponen gaji dinamis (pendapatan & potongan)
     */
    public function up(): void
    {
        Schema::create('gaji_komponens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_gaji');
            $table->foreign('id_gaji')
                ->references('id')->on('gajis')
                ->onDelete('cascade');

            $table->string('nama_komponen', 100)
                ->comment('Gaji Pokok | Tunjangan | Potongan Alpha | dll');
            $table->enum('tipe', ['pendapatan', 'potongan'])
                ->comment('Klasifikasi komponen');
            $table->decimal('nominal', 12, 2)->default(0.00);

            $table->timestamps();

            $table->index('id_gaji');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gaji_komponens');
    }
};
