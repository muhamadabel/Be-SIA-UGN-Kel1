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
        Schema::create('book_orders', function (Blueprint $table) {
            $table->id('id_book_order');

            // Foreign Key ke users_si (peminjam)
            $table->unsignedBigInteger('id_user');
            $table->foreign('id_user')->references('id_user_si')->on('users_si')->onDelete('cascade');

            // Foreign Key ke books
            $table->unsignedBigInteger('id_book');
            $table->foreign('id_book')->references('id_book')->on('books')->onDelete('cascade');

            $table->enum('status', ['ordered', 'borrowed', 'returned', 'cancelled'])->default('ordered');
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('borrowed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_orders');
    }
};
