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
        Schema::create('grade_conversions', function (Blueprint $table) {
            $table->id('id_grades');
            $table->unsignedInteger('min_grade')->comment('Nilai minimal (0 - 100)');
            $table->unsignedInteger('max_grade')->comment('Nilai maksimal (0 - 100)');
            $table->string('letter', 3)->unique()->comment('Nilai huruf: A, A-, B+, dst.');
            $table->decimal('ip_skor', 3, 2)->comment('Indeks prestasi (0.00 - 4.00)');
            $table->timestamps();

            $table->index(['min_grade', 'max_grade'], 'idx_score_range');
            $table->index('letter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_conversions');
    }
};
