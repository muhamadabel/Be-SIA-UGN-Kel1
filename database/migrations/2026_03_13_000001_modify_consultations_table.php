<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('consultation_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('location', 255)->nullable()->after('end_time');
            $table->text('next_task')->nullable()->after('attachment');
            $table->unsignedTinyInteger('progress')->default(0)->after('next_task');
        });

        // Normalisasi data: pending/rejected -> on_going
        DB::table('consultations')->where('status', 'pending')->update(['status' => 'on_going']);
        DB::table('consultations')->where('status', 'rejected')->update(['status' => 'on_going']);

        // Ubah enum status (driver-aware: MySQL ENUM / PostgreSQL CHECK)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consultations DROP CONSTRAINT IF EXISTS consultations_status_check');
            DB::statement('ALTER TABLE consultations ALTER COLUMN status TYPE VARCHAR(20)');
            DB::statement("ALTER TABLE consultations ALTER COLUMN status SET DEFAULT 'on_going'");
            DB::statement("ALTER TABLE consultations ADD CONSTRAINT consultations_status_check CHECK (status IN ('on_going','finished'))");
        } else {
            DB::statement("ALTER TABLE consultations MODIFY COLUMN status ENUM('on_going','finished') DEFAULT 'on_going'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consultations DROP CONSTRAINT IF EXISTS consultations_status_check');
            DB::statement("ALTER TABLE consultations ADD CONSTRAINT consultations_status_check CHECK (status IN ('pending','on_going','finished','rejected'))");
        } else {
            DB::statement("ALTER TABLE consultations MODIFY COLUMN status ENUM('pending','on_going','finished','rejected') DEFAULT 'pending'");
        }

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'location', 'next_task', 'progress']);
        });
    }
};
