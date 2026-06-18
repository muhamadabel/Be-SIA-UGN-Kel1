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
        Schema::create('student_profiles', function (Blueprint $table) {
            // PK: id_profile
            $table->id('id_profile');

            // FK: id_user (unique, menandakan relasi one-to-one)
            $table->unsignedBigInteger('id_user_si')->unique();
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');
            // $table->foreign('id_programs')->references('id')->on('programs')->onDelete('cascade');

            // Kolom-kolom lainnya
            $table->string('registration_number', 20)->unique();
            $table->string('registration_status', 50);
            $table->string('full_name');
            $table->string('gender', 20)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nik', 16)->nullable()->unique();
            $table->string('birth_certificate_number', 50)->nullable();
            $table->string('no_kk', 16)->nullable();
            $table->string('citizenship', 50)->nullable();
            $table->integer('birth_order')->nullable();
            $table->integer('number_of_siblings')->nullable();
            $table->text('full_address')->nullable();
            $table->string('dusun', 100)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('city_regency', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('previous_school', 100)->nullable();
            $table->string('graduation_status', 20)->nullable();
            $table->string('last_ijazah', 20)->nullable();

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
        Schema::dropIfExists('student_profiles');
    }
};
