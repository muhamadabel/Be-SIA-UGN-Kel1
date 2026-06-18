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
        Schema::table('notifications', function (Blueprint $table) {
            // Foreign Key ke thesis_lecturer (untuk notifikasi bimbingan TA)
            $table->unsignedBigInteger('id_thesis_lecturer')->nullable()->after('id_correspondence');
            $table->foreign('id_thesis_lecturer')->references('id_thesis_lecturer')->on('thesis_lecturer')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['id_thesis_lecturer']);
            $table->dropColumn('id_thesis_lecturer');
        });
    }
};
