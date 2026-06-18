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
        Schema::table('grades', function (Blueprint $table) {
            // Tambah kolom id_class setelah id_subject
            $table->unsignedBigInteger('id_class')
                ->nullable() // Nullable dulu untuk data lama
                ->after('id_subject');
            
            // Tambah foreign key constraint
            $table->foreign('id_class')
                ->references('id_class')
                ->on('classes')
                ->onDelete('cascade'); // Hapus nilai jika kelas dihapus
            
            // Tambah index untuk performa query
            $table->index('id_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            // Drop foreign key dulu
            $table->dropForeign(['id_class']);
            
            // Drop kolom
            $table->dropColumn('id_class');
        });
    }
};
