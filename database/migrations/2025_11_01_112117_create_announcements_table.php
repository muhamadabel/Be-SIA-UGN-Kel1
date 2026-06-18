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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id('id_announcement');

            // Foreign Key untuk kelas (nullable - bisa untuk semua kelas atau kelas tertentu)
            $table->unsignedBigInteger('id_class')->nullable();
            $table->foreign('id_class')->references('id_class')->on('classes')->onDelete('cascade');

            // Konten pengumuman
            $table->text('message');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('announcements');
    }
};
