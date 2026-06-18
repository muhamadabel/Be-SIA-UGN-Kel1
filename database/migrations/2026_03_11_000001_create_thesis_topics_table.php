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
        Schema::create('thesis_topics', function (Blueprint $table) {
            $table->id('id_thesis_topic');

            // Dosen pemilik topik
            $table->unsignedBigInteger('id_lecturer');
            $table->foreign('id_lecturer')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Program studi
            $table->unsignedBigInteger('id_program');
            $table->foreign('id_program')->references('id_program')->on('programs')->onDelete('cascade');

            $table->string('topic', 255);
            $table->string('title_ind', 255);
            $table->string('title_eng', 255);
            $table->enum('status', ['draft', 'available', 'taken', 'archived'])->default('draft');
            $table->longText('description');
            $table->unsignedInteger('quota')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_topics');
    }
};
