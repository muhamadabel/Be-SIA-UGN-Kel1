<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            // Tambah kolom baru
            $table->time('start_time')->nullable()->after('consultation_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('location', 255)->nullable()->after('end_time');
            $table->text('next_task')->nullable()->after('attachment');
            $table->unsignedTinyInteger('progress')->default(0)->after('next_task');
        });

        // Ubah enum status: hapus 'pending' dan 'rejected', sisakan 'on_going' dan 'finished'
        // Update data existing yang berstatus pending/rejected ke on_going
        DB::table('consultations')->where('status', 'pending')->update(['status' => 'on_going']);
        DB::table('consultations')->where('status', 'rejected')->update(['status' => 'on_going']);

        // Ubah kolom enum
        DB::statement("ALTER TABLE consultations MODIFY COLUMN status ENUM('on_going', 'finished') DEFAULT 'on_going'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan enum ke semula
        DB::statement("ALTER TABLE consultations MODIFY COLUMN status ENUM('pending', 'on_going', 'finished', 'rejected') DEFAULT 'pending'");

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'location', 'next_task', 'progress']);
        });
    }
};
