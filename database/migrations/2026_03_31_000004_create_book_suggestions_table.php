<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('book_suggestions', function (Blueprint $table) {
            $table->id('id_book_suggestion');

            // Foreign Key ke users_si (pengusul)
            $table->unsignedBigInteger('id_user');
            $table->foreign('id_user')->references('id_user_si')->on('users_si')->onDelete('cascade');

            $table->string('title');
            $table->string('author');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_suggestions');
    }
};
