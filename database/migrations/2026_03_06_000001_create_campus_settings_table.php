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
        Schema::create('campus_settings', function (Blueprint $table) {
            $table->id('id_setting');

            $table->string('nama_kampus');
            $table->decimal('latitude', 10, 8)->comment('Titik pusat GPS kampus (lintang)');
            $table->decimal('longitude', 11, 8)->comment('Titik pusat GPS kampus (bujur)');
            $table->integer('radius_meter')->comment('Radius validasi dalam meter');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campus_settings');
    }
};
