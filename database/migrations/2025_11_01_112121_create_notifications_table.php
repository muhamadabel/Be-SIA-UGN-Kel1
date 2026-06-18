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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('id_notification');

            // Foreign Key untuk user (penerima notifikasi)
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Foreign Key untuk conversation (nullable - untuk notifikasi chat)
            $table->unsignedBigInteger('id_conversation')->nullable();
            $table->foreign('id_conversation')->references('id_conversation')->on('chat_conversations')->onDelete('cascade');

            // Foreign Key untuk message (nullable - untuk notifikasi chat)
            $table->unsignedBigInteger('id_message')->nullable();
            $table->foreign('id_message')->references('id_message')->on('chat_messages')->onDelete('cascade');

            // Foreign Key untuk announcement (nullable - untuk notifikasi pengumuman)
            $table->unsignedBigInteger('id_announcement')->nullable();
            $table->foreign('id_announcement')->references('id_announcement')->on('announcements')->onDelete('cascade');

            // Timestamps
            $table->timestamp('sent_at')->useCurrent();
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
        Schema::dropIfExists('notifications');
    }
};
