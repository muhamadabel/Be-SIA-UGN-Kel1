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
        Schema::create('thesis_lecturer', function (Blueprint $table) {
            $table->id('id_thesis_lecturer');

            // Tugas akhir mahasiswa
            $table->unsignedBigInteger('id_student_thesis');
            $table->foreign('id_student_thesis')->references('id_student_thesis')->on('student_thesis')->onDelete('cascade');

            // Dosen yang diajukan
            $table->unsignedBigInteger('id_lecturer');
            $table->foreign('id_lecturer')->references('id_user_si')->on('users_si')->onDelete('cascade');

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->text('student_note')->nullable();
            $table->text('rejection_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_lecturer');
    }
};
