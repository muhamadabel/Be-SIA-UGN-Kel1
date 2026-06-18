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
Schema::create('academic_periods', function (Blueprint $table) {
    $table->id('id_academic_period');
    $table->string('name')->unique()->comment('Contoh: Semester Ganjil 2024/2025');
    $table->date('start_date');
    $table->date('end_date');
    $table->boolean('is_active')->default(false)->comment('Hanya 1 periode boleh aktif');
    $table->timestamps();

    // untuk mempercepat query database untuk periode yang aktif
    $table->index('is_active');
    $table->index(['start_date', 'end_date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
