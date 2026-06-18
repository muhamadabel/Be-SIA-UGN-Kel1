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
        // Tabel perantara (pivot) untuk dosen dan kelas
        Schema::create('lecturer_class', function (Blueprint $table) {
            // FK untuk user (dosen)
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // FK untuk kelas
            $table->unsignedBigInteger('id_class');
            $table->foreign('id_class')->references('id_class')->on('classes')->onDelete('cascade');

            // Composite primary key untuk mencegah duplikasi
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
        Schema::dropIfExists('lecturer_class');
    }
};
