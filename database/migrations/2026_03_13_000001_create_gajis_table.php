<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * C.4 - Header slip gaji bulanan dosen
     */
    public function up(): void
    {
        Schema::create('gajis', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')
                ->references('id_user_si')->on('users_si')
                ->onDelete('cascade');

            $table->tinyInteger('bulan')->unsigned()->comment('1-12');
            $table->smallInteger('tahun')->unsigned();

            $table->decimal('total_pendapatan', 12, 2)->default(0.00);
            $table->decimal('total_potongan', 12, 2)->default(0.00);
            $table->decimal('gaji_bersih', 12, 2)->default(0.00);

            $table->timestamps();

            // Satu slip per dosen per bulan per tahun
            $table->unique(['id_user_si', 'bulan', 'tahun'], 'gaji_dosen_bulan_tahun_unique');

            $table->index(['id_user_si', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gajis');
    }
};
