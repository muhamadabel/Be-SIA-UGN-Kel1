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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id('id_message');

            // Foreign Key untuk percakapan
            $table->unsignedBigInteger('id_conversation');
            $table->foreign('id_conversation')->references('id_conversation')->on('chat_conversations')->onDelete('cascade');

            // Foreign Key untuk user (pengirim)
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Konten pesan
            $table->text('message');
            
            // Read status timestamp (null = unread)
            $table->timestamp('read_at')->nullable();
            
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
        Schema::dropIfExists('chat_messages');
    }
};
