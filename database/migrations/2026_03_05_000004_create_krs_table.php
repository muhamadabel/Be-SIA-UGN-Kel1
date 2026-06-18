<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel krs menyimpan pengajuan Kartu Rencana Studi setiap mahasiswa.
     *
     * Flow:
     *  1. Manager membuka sesi KRS (krs_sessions).
     *  2. Mahasiswa memilih kelas yang tersedia, KRS dibuat dengan status pending.
     *  3. Manager mereview dan approve/reject setiap entri KRS.
     *  4. Manager menutup sesi KRS.
     *
     * Setiap entri KRS terikat pada satu sesi (id_krs_session), satu mahasiswa,
     * dan satu kelas. Mahasiswa tidak dapat mendaftar kelas yang sama dua kali
     * dalam satu sesi.
     */
    public function up(): void
    {
        Schema::create('krs', function (Blueprint $table) {
            $table->id('id_krs');

            // Sesi KRS tempat mahasiswa mendaftar
            $table->unsignedBigInteger('id_krs_session');
            $table->foreign('id_krs_session')
                  ->references('id_krs_session')
                  ->on('krs_sessions')
                  ->onDelete('cascade');

            // Mahasiswa yang mengajukan KRS
            $table->unsignedBigInteger('id_user_si');
            $table->foreign('id_user_si')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('cascade');

            // Periode akademik KRS ini diajukan (denormalisasi untuk kemudahan query)
            $table->unsignedBigInteger('id_academic_period');
            $table->foreign('id_academic_period')
                  ->references('id_academic_period')
                  ->on('academic_periods')
                  ->onDelete('cascade');

            // Kelas yang dipilih oleh mahasiswa
            $table->unsignedBigInteger('id_class');
            $table->foreign('id_class')
                  ->references('id_class')
                  ->on('classes')
                  ->onDelete('cascade');

            // Mata kuliah dari kelas yang dipilih (denormalisasi dari classes.id_subject untuk performa query)
            $table->unsignedBigInteger('id_subject');
            $table->foreign('id_subject')
                  ->references('id_subject')
                  ->on('subjects')
                  ->onDelete('cascade');

            // Status KRS: pending (belum diproses), approved (disetujui), rejected (ditolak)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Manager/admin yang memproses KRS ini (nullable, diisi saat diproses)
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->foreign('processed_by')
                  ->references('id_user_si')
                  ->on('users_si')
                  ->onDelete('set null');

            // Waktu ketika KRS diproses oleh manager/admin
            $table->timestamp('processed_at')->nullable();

            // Alasan penolakan (diisi jika status = rejected)
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Satu mahasiswa tidak dapat mendaftar kelas yang sama dua kali
            // dalam satu sesi KRS
            $table->unique(['id_user_si', 'id_class', 'id_krs_session'], 'krs_student_class_session_unique');

            // Index untuk mempercepat query umum
            $table->index(['id_krs_session', 'id_user_si'], 'krs_session_student_idx');
            $table->index(['id_user_si', 'id_academic_period'], 'krs_student_period_idx');
            $table->index(['id_class', 'status'], 'krs_class_status_idx');
            $table->index(['id_subject', 'status'], 'krs_subject_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs');
    }
};
