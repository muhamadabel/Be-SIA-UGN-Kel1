<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\StudentThesis;
use App\Models\ThesisLecturer;
use App\Models\ThesisSupervisor;
use App\Models\ThesisTopic;
use App\Models\User_si;
use App\Events\NewNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThesisService
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Approve permintaan pembimbing dari mahasiswa.
     *
     * - Update thesis_lecturer → accepted
     * - Insert ke thesis_supervisors
     * - Update student_thesis → on_progress
     * - Cek & update quota topik jika ada
     * - Kirim notifikasi + push ke mahasiswa
     */
    public function approveLecturerRequest(ThesisLecturer $request): void
    {
        DB::transaction(function () use ($request) {
            $request->update(['status' => 'accepted']);

            ThesisSupervisor::create([
                'id_student_thesis' => $request->id_student_thesis,
                'id_lecturer'       => $request->id_lecturer,
            ]);

            $thesis = $request->studentThesis;
            $thesis->update(['status' => 'on_progress']);

            // Cek quota topik dosen jika TA berasal dari topik
            if ($thesis->id_thesis_topic) {
                $this->checkAndUpdateTopicQuota($thesis->thesisTopic);
            }

            // Notifikasi ke mahasiswa
            $this->notifyStudent(
                $thesis->id_student,
                $request->id_thesis_lecturer,
                'Pengajuan Bimbingan Disetujui',
                "Dosen telah menyetujui pengajuan bimbingan TA Anda."
            );
        });
    }

    /**
     * Reject permintaan pembimbing dari mahasiswa.
     *
     * - Update thesis_lecturer → rejected
     * - Kirim notifikasi + push ke mahasiswa
     */
    public function rejectLecturerRequest(ThesisLecturer $request, string $rejectionNote): void
    {
        DB::transaction(function () use ($request, $rejectionNote) {
            $request->update([
                'status'         => 'rejected',
                'rejection_note' => $rejectionNote,
            ]);

            $thesis = $request->studentThesis;

            $this->notifyStudent(
                $thesis->id_student,
                $request->id_thesis_lecturer,
                'Pengajuan Bimbingan Ditolak',
                "Dosen menolak pengajuan bimbingan TA Anda. Catatan: {$rejectionNote}"
            );
        });
    }

    /**
     * Buat thesis dari topik dosen yang dipilih mahasiswa.
     *
     * - Insert student_thesis
     * - Insert thesis_lecturer (pending) ke dosen pemilik topik
     * - Kirim notifikasi + push ke dosen
     */
    public function createThesisFromTopic(User_si $student, ThesisTopic $topic, array $data): StudentThesis
    {
        return DB::transaction(function () use ($student, $topic, $data) {
            $thesis = StudentThesis::create([
                'id_student'      => $student->id_user_si,
                'id_program'      => $student->id_program,
                'id_thesis_topic' => $topic->id_thesis_topic,
                'topic'           => $topic->topic,
                'title_ind'       => $topic->title_ind,
                'title_eng'       => $topic->title_eng,
                'status'          => 'proposing',
                'description'     => $topic->description,
                'attachment_proposal' => $data['attachment_proposal'] ?? null,
            ]);

            $lecturerRequest = ThesisLecturer::create([
                'id_student_thesis' => $thesis->id_student_thesis,
                'id_lecturer'       => $topic->id_lecturer,
                'status'            => 'pending',
                'student_note'      => $data['student_note'] ?? null,
            ]);

            // Notifikasi ke dosen pemilik topik
            $this->notifyLecturer(
                $topic->id_lecturer,
                $lecturerRequest->id_thesis_lecturer,
                'Permintaan Bimbingan TA Baru',
                "Mahasiswa {$student->name} meminta bimbingan untuk topik '{$topic->title_ind}'."
            );

            return $thesis;
        });
    }

    /**
     * Cek quota topik dan ubah status ke 'taken' jika sudah penuh.
     */
    public function checkAndUpdateTopicQuota(ThesisTopic $topic): void
    {
        $acceptedCount = StudentThesis::where('id_thesis_topic', $topic->id_thesis_topic)
            ->where('status', '!=', 'proposing')
            ->count();

        if ($acceptedCount >= $topic->quota) {
            $topic->update(['status' => 'taken']);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function notifyStudent(int $studentId, int $thesisLecturerId, string $title, string $body): void
    {
        try {
            $notification = Notification::create([
                'id_user_si'        => $studentId,
                'id_thesis_lecturer' => $thesisLecturerId,
                'sent_at'           => now(),
            ]);

            broadcast(new NewNotification($studentId, $notification->load('thesisLecturer')));

            $this->pushService->sendToUser($studentId, $title, $body, [
                'type'               => 'thesis_bimbingan',
                'id_thesis_lecturer' => $thesisLecturerId,
            ]);
        } catch (\Exception $e) {
            Log::error('ThesisService: gagal kirim notifikasi ke mahasiswa', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function notifyLecturer(int $lecturerId, int $thesisLecturerId, string $title, string $body): void
    {
        try {
            $notification = Notification::create([
                'id_user_si'        => $lecturerId,
                'id_thesis_lecturer' => $thesisLecturerId,
                'sent_at'           => now(),
            ]);

            broadcast(new NewNotification($lecturerId, $notification->load('thesisLecturer')));

            $this->pushService->sendToUser($lecturerId, $title, $body, [
                'type'               => 'thesis_bimbingan',
                'id_thesis_lecturer' => $thesisLecturerId,
            ]);
        } catch (\Exception $e) {
            Log::error('ThesisService: gagal kirim notifikasi ke dosen', [
                'lecturer_id' => $lecturerId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
