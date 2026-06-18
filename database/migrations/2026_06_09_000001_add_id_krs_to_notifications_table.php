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
            // Foreign Key ke krs (nullable - untuk notifikasi approve/reject KRS)
            $table->unsignedBigInteger('id_krs')->nullable()->after('id_correspondence');
            $table->foreign('id_krs')->references('id_krs')->on('krs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['id_krs']);
            $table->dropColumn('id_krs');
        });
    }
};
