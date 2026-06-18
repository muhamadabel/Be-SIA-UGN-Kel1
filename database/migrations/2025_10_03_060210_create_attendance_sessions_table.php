<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id('id_qr');

            // Foreign Key untuk jadwal
            $table->unsignedBigInteger('id_schedule');
            $table->foreign('id_schedule')->references('id_schedule')->on('schedules')->onDelete('cascade');

            $table->string('key')->unique(); // Kunci unik untuk QR code
            $table->timestamp('time_start');
            $table->timestamp('time_end')->nullable();
            $table->string('name_agenda');
            
            $table->timestamps(); // Ini akan membuat kolom 'created_at' yang setara dengan kolom 'time' Anda
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
