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
        Schema::create('chat_participants', function (Blueprint $table) {
            // Foreign Key untuk percakapan
            $table->unsignedBigInteger('id_conversation');
            $table->foreign('id_conversation')->references('id_conversation')->on('chat_conversations')->onDelete('cascade');

            // Foreign Key untuk user (peserta)
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Menjadikan kombinasi keduanya sebagai primary key untuk mencegah duplikasi
            $table->primary(['id_conversation', 'id_user_si']);
            
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
        Schema::dropIfExists('chat_participants');
    }
};
