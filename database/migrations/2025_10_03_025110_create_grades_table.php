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
        Schema::create('grades', function (Blueprint $table) {
            // PK: id_grades : int(10)
            $table->id('id_grades');

            // FK: id_student : int(10) -> merujuk ke id di tabel users_si
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            
            // FK: id_subject : int(10) -> merujuk ke id di tabel subjects
            $table->unsignedBigInteger('id_subject');
            $table->foreign('id_subject')->references('id_subject')->on('subjects')->onDelete('cascade');

            // grade : varchar(3)
            $table->string('grade', 3)->nullable();

            $table->timestamps();

            // Menambahkan constraint unique untuk memastikan seorang mahasiswa
            // hanya bisa punya satu nilai untuk satu mata kuliah.
            $table->unique(['id_user_si', 'id_subject']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grades');
    }
};
