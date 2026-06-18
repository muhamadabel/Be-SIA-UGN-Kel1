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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id('id_consultation');

            // Relasi ke thesis_supervisors (dosen pembimbing + mahasiswa bimbingan)
            $table->unsignedBigInteger('id_supervisor');
            $table->foreign('id_supervisor')->references('id_supervisor')->on('thesis_supervisors')->onDelete('cascade');

            $table->date('consultation_date');
            $table->string('subject', 255);
            $table->text('student_notes')->nullable();
            $table->text('lecturer_notes')->nullable();
            $table->string('attachment', 255)->nullable();
            $table->enum('status', ['pending', 'on_going', 'finished', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
