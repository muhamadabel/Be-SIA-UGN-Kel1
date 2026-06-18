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
        Schema::create('student_thesis', function (Blueprint $table) {
            $table->id('id_student_thesis');

            // Mahasiswa pengaju
            $table->unsignedBigInteger('id_student');
            $table->foreign('id_student')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Program studi mahasiswa
            $table->unsignedBigInteger('id_program');
            $table->foreign('id_program')->references('id_program')->on('programs')->onDelete('cascade');

            // Topik TA dosen (nullable jika mahasiswa mengajukan sendiri)
            $table->unsignedBigInteger('id_thesis_topic')->nullable();
            $table->foreign('id_thesis_topic')->references('id_thesis_topic')->on('thesis_topics')->onDelete('set null');

            $table->string('topic', 255)->nullable();
            $table->string('title_ind', 255);
            $table->string('title_eng', 255);
            $table->enum('status', ['proposing', 'on_progress', 'revision', 'finished'])->default('proposing');
            $table->longText('description');
            $table->string('attachment_proposal', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_thesis');
    }
};
