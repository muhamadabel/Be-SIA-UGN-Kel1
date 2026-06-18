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
        Schema::create('staff_profiles', function (Blueprint $table) {
            // PK: id_staff_profile
            $table->id('id_staff_profile');

            // FK: id_user (unique, menandakan relasi one-to-one)
            $table->unsignedBigInteger('id_user_si')->unique();
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Kolom-kolom lainnya
            $table->string('full_name');
            $table->string('employee_id_number', 50)->unique();
            $table->string('position', 100)->nullable();

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
        Schema::dropIfExists('staff_profiles');
    }
};
