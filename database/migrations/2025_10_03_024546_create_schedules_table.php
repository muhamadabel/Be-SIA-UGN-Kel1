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
        Schema::create('schedules', function (Blueprint $table) {
            // PK: id_schedule : int(10)
            $table->id('id_schedule');

            // FK: id_class : int(10)
            $table->unsignedBigInteger('id_class');
            $table->foreign('id_class')->references('id_class')->on('classes')->onDelete('cascade');

            $table->date('date');

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
        Schema::dropIfExists('schedules');
    }
};
