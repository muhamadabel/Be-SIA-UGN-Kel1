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
        Schema::create('student_class', function (Blueprint $table) {
            // Foreign key untuk user
            $table->unsignedBigInteger('id_user_si')->unique();
            $table->foreign('id_user_si')->references('id_user_si')->unique()->on('users_si')->onDelete('cascade');

            // Foreign key untuk class
            $table->unsignedBigInteger('id_class');
            $table->foreign('id_class')
                  ->references('id_class')
                  ->on('classes')
                  ->onDelete('cascade');

            // Composite primary key (id_user_si + id_class)
            $table->primary(['id_user_si', 'id_class']);
            
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
        Schema::dropIfExists('student_class');
    }
};

