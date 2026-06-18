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
            // Foreign Key ke correspondences (nullable - untuk notifikasi pengaduan)
            $table->unsignedBigInteger('id_correspondence')->nullable()->after('id_announcement');
            $table->foreign('id_correspondence')->references('id_correspondence')->on('correspondences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['id_correspondence']);
            $table->dropColumn('id_correspondence');
        });
    }
};
