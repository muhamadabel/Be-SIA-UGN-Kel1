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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id('id_conversation');

            // --- SEMUA PERUBAHAN DIGABUNG DI SINI ---
            // Tambahkan kolom 'type' untuk membedakan grup/privat
            $table->enum('type', ['group', 'private'])->default('group');
            
            // Jadikan id_class nullable karena chat privat tidak terikat pada kelas
            $table->foreignId('id_class')->nullable()->constrained('classes', 'id_class')->onDelete('cascade');
            
            // Foreign key ke tabel users_si untuk mencatat siapa yang memulai percakapan
            $table->foreignId('id_initiator')->constrained('users_si', 'id_user_si')->onDelete('cascade');

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
        Schema::dropIfExists('chat_conversations');
    }
};

