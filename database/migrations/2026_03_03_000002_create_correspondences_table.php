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
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id('id_correspondence');

            // Foreign Key ke users_si (pengaju persuratan)
            $table->unsignedBigInteger('id_user');
            $table->foreign('id_user')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Foreign Key ke correspondence_categories
            $table->unsignedBigInteger('id_category');
            $table->foreign('id_category')->references('id_category')->on('correspondence_categories')->onDelete('restrict');

            // Foreign Key ke correspondence_recipient
            $table->unsignedBigInteger('id_recipient');
            $table->foreign('id_recipient')->references('id_recipient')->on('correspondence_recipient')->onDelete('restrict');

            $table->string('title');
            $table->longText('correspondence_body');
            $table->enum('status', ['submitted', 'process', 'resolved', 'rejected'])->default('submitted');
            $table->string('attachment')->nullable();

            // Kolom respons dari admin/manager
            $table->longText('response_text')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};
