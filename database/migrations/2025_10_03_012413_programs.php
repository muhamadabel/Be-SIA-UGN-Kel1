<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('programs', function (Blueprint $table) {
            // Sesuai konvensi Laravel, primary key bernama 'id'.
            // Ini adalah 'id_program' dari diagram Anda.
            $table->id('id_program');

            // Ini adalah 'name_program' dari diagram Anda.
            $table->string('name');

            // Timestamps 'created_at' dan 'updated_at' adalah praktik terbaik.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('programs');
    }
};
