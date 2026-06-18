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
        // ------------------------------------------------------------------------------------
        // PERINGATAN: Fitur ini OPSIONAL (Dimatikan secara default).
        // ------------------------------------------------------------------------------------
        // Jika kamu ingin menghapus tabel bawaan Laravel (seperti 'users'),
        // silakan HAPUS tanda komentar (//) pada baris-baris di bawah ini.
        
        // Schema::dropIfExists('users');

        // ------------------------------------------------------------------------------------
        // yg bawah ini masih g tau boleh di drop atau ngga. tapi simpen dulu. siapatau perlu
        // soalnya yang cache dan cache_locks dipake buat spatie 
        // Schema::dropIfExists('password_reset_tokens');
        // Schema::dropIfExists('sessions');
        // Schema::dropIfExists('jobs');
        // Schema::dropIfExists('failed_jobs');
        // Schema::dropIfExists('job_batches');
        // ------------------------------------------------------------------------------------
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kosongkan saja, karena kita tidak perlu me-restore tabel default 
        // jika migration ini di-rollback (biasanya untuk bersih-bersih di awal).
    }
};