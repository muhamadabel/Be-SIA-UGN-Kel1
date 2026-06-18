<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Tabel ini diperlukan untuk foreign key 'id_subject' di tabel classes
        Schema::create('subjects', function (Blueprint $table) {
            $table->id('id_subject'); // Ini akan membuat primary key 'id'
            $table->string('name_subject'); 
            $table->string('code_subject')->unique();
            $table->integer('sks'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('subjects');
    }
};

