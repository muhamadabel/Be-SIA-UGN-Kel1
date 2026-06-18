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
        // Membuat tabel 'classes' persis seperti diagram Anda
        Schema::create('classes', function (Blueprint $table) {
            // PK: id_class : int(10)
            $table->id('id_class');

            // FK: id_subject : int(10)
            // Menggunakan konvensi Laravel untuk foreign key akan lebih mudah,
            // tapi kita ikuti diagram Anda.
            $table->unsignedBigInteger('id_subject');
            $table->foreign('id_subject')->references('id_subject')->on('subjects')->onDelete('cascade');
            // FK: id_academic_period : int(10)
            $table->foreignId('id_academic_period')->constrained('academic_periods', 'id_academic_period')->onDelete('cascade');
            // code_class : varchar(10)
            $table->string('code_class', 10);

            // member_class : int(5)
            $table->integer('member_class');
            $table->integer('day_of_week'); // 1=Senin, 2=Selasa, ... 7=Minggu
            $table->time('start_time');
            $table->time('end_time');

            // is_active : boolean default false
            $table->boolean('is_active')->default(false);
            $table->unique(['id_subject', 'code_class', 'id_academic_period']);
            
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
        Schema::dropIfExists('classes');
    }
};
