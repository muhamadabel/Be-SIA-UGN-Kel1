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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id('id_device_token');
            $table->unsignedBigInteger('id_user_si');
            $table->string('expo_push_token')->unique(); // ExponentPushToken[xxx]
            $table->string('device_id')->nullable(); // Device identifier
            $table->string('device_name')->nullable(); // Device name/model
            $table->string('platform')->default('android'); // android/ios
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('id_user_si')
                ->references('id_user_si')
                ->on('users_si')
                ->onDelete('cascade');

            // Index untuk query performance
            $table->index('id_user_si');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
