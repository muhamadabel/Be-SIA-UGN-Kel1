<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan FK id_tuition_payment ke tabel notifications
     * untuk mendukung notifikasi terkait pembayaran UKT.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tuition_payment')->nullable()->after('id_book_suggestion');
            $table->foreign('id_tuition_payment')->references('id_tuition_payment')->on('tuition_payments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['id_tuition_payment']);
            $table->dropColumn('id_tuition_payment');
        });
    }
};
