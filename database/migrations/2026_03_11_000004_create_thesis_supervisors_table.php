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
        Schema::create('thesis_supervisors', function (Blueprint $table) {
            $table->id('id_supervisor');

            // Tugas akhir yang dibimbing
            $table->unsignedBigInteger('id_student_thesis');
            $table->foreign('id_student_thesis')->references('id_student_thesis')->on('student_thesis')->onDelete('cascade');

            // Dosen pembimbing yang disetujui
            $table->unsignedBigInteger('id_lecturer');
            $table->foreign('id_lecturer')->references('id_user_si')->on('users_si')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_supervisors');
    }
};
