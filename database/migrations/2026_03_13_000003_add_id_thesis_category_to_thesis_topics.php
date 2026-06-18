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
        Schema::table('thesis_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('id_thesis_category')->nullable()->after('id_program');
            $table->foreign('id_thesis_category')->references('id_thesis_category')->on('thesis_categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_topics', function (Blueprint $table) {
            $table->dropForeign(['id_thesis_category']);
            $table->dropColumn('id_thesis_category');
        });
    }
};
