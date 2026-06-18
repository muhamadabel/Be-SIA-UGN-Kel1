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
        Schema::create('books', function (Blueprint $table) {
            $table->id('id_book');

            $table->string('title');
            $table->string('author');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('isbn')->nullable();

            // Foreign Key ke book_categories
            $table->unsignedBigInteger('id_book_category');
            $table->foreign('id_book_category')->references('id_book_category')->on('book_categories')->onDelete('restrict');

            $table->unsignedInteger('total_stock')->default(0);
            $table->unsignedInteger('available_stock')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
