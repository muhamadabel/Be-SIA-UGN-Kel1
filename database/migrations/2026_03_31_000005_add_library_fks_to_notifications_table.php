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
            $table->unsignedBigInteger('id_book_order')->nullable()->after('id_thesis_lecturer');
            $table->foreign('id_book_order')->references('id_book_order')->on('book_orders')->onDelete('set null');

            $table->unsignedBigInteger('id_book_suggestion')->nullable()->after('id_book_order');
            $table->foreign('id_book_suggestion')->references('id_book_suggestion')->on('book_suggestions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['id_book_order']);
            $table->dropColumn('id_book_order');

            $table->dropForeign(['id_book_suggestion']);
            $table->dropColumn('id_book_suggestion');
        });
    }
};
