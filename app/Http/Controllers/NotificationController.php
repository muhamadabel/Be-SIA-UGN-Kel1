<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use App\Models\Announcement;
use App\Services\PushNotificationService;
use App\Events\NewNotification;

class NotificationController extends Controller
{
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Get all notifications for authenticated user
     * GET /api/notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Notification::where('id_user_si', $user->id_user_si)
            ->with([
                'conversation',
                'message.sender:id_user_si,name',
                'announcement.class:id_class,code_class',
                'correspondence.category:id_category,name,slug',
                'correspondence.recipient:id_recipient,name,slug',
                'tuitionPayment.tuitionFee.academicPeriod:id_academic_period,name',
                'krs.subject:id_subject,name_subject,code_subject,sks',
                'krs.krsClass:id_class,code_class',
            ])
            ->orderBy('sent_at', 'desc');

        if ($request->has('status')) {
            if ($request->status === 'unread') {
                $query->unread();
            } elseif ($request->status === 'read') {
                $query->read();
            }
        }

        if ($request->has('type')) {
            if ($request->type === 'chat') {
                $query->whereNotNull('id_message');
            } elseif ($request->type === 'announcement') {
                $query->whereNotNull('id_announcement');
            } elseif ($request->type === 'correspondence') {
                $query->whereNotNull('id_correspondence');
            } elseif ($request->type === 'tuition') {
                $query->whereNotNull('id_tuition_payment');
            } elseif ($request->type === 'krs') {
                $query->whereNotNull('id_krs');
            }
        }

        $notifications = $query->get();

        // Format notifikasi dengan info lengkap
        $formattedNotifications = $notifications->map(function ($notification) use ($user) {
            $type = null;
            $title = '';
            $message = '';
            $sender = '';
            $metadata = [];

            if ($notification->id_message) {
                $type = 'chat';
                $sender = $notification->message->sender->name ?? 'Unknown';
                $title = "Pesan dari {$sender}";
                $message = $notification->message->message ?? '';
                $metadata = [
                    'id_conversation' => (int)$notification->id_conversation,
                    'id_message' => (int)$notification->id_message,
                ];

                if ($notification->conversation && $notification->conversation->id_class) {
                    $classInfo = DB::table('classes')
                        ->where('id_class', $notification->conversation->id_class)
                        ->first();

                    if ($classInfo) {
                        $metadata['id_class'] = (int)$classInfo->id_class;
                        $metadata['class_code'] = $classInfo->code_class;
                    }
                }
            } elseif ($notification->id_announcement) {
                $type = 'announcement';
                $announcement = $notification->announcement;

                $title = $announcement->title ?? ($announcement->class
                    ? "Pengumuman - {$announcement->class->code_class}"
                    : "Pengumuman Umum");

                $message = $announcement->message ?? '';
                $metadata = [
                    'id_announcement' => (int)$notification->id_announcement,
                    'id_class' => $announcement->id_class ? (int)$announcement->id_class : null,
                ];

                if ($announcement->id_class) {
                    $classInfo = DB::table('classes')
                        ->select('classes.*', 'subjects.name_subject', 'subjects.code_subject')
                        ->leftJoin('subjects', 'classes.id_subject', '=', 'subjects.id_subject')
                        ->where('classes.id_class', $announcement->id_class)
                        ->first();

                    if ($classInfo) {
                        $metadata['class_code'] = $classInfo->code_class;
                        $metadata['subject_name'] = $classInfo->name_subject;
                        $metadata['subject_code'] = $classInfo->code_subject;
                        $metadata['id_subject'] = $classInfo->id_subject ? (int)$classInfo->id_subject : null;

                        $lecturer = DB::table('lecturer_class')
                            ->select('staff_profiles.full_name')
                            ->join('staff_profiles', 'lecturer_class.id_user_si', '=', 'staff_profiles.id_user_si')
                            ->where('lecturer_class.id_class', $classInfo->id_class)
                            ->first();

                        if ($lecturer) {
                            $metadata['lecturer_name'] = $lecturer->full_name;
                        }

                        if ($user->role === 'mahasiswa') {
                            $studentInfo = DB::table('student_profiles')
                                ->where('id_user_si', $user->id_user_si)
                                ->first();

                            if ($studentInfo) {
                                $metadata['student_name'] = $studentInfo->full_name;
                                $metadata['student_nim'] = $studentInfo->registration_number;
                            }
                        }
                    }
                }

                $sender = 'System';
            } elseif ($notification->id_correspondence) {
                $type           = 'correspondence';
                $correspondence = $notification->correspondence;

                $statusLabels = [
                    'submitted' => 'Diterima',
                    'process'   => 'Sedang Diproses',
                    'resolved'  => 'Diselesaikan',
                    'rejected'  => 'Ditolak',
                ];
                $statusLabel = $statusLabels[$correspondence->status] ?? $correspondence->status;

                $title   = 'Update Status Surat: ' . ($correspondence->title ?? '-');
                $message = "Status surat Anda sekarang: {$statusLabel}.";
                if ($correspondence->response_text) {
                    $message .= ' Pengelola telah memberikan respons.';
                }

                $metadata = [
                    'id_correspondence' => (int) $notification->id_correspondence,
                    'title'             => $correspondence->title,
                    'status'            => $correspondence->status,
                    'responded_at'      => $correspondence->responded_at?->toIso8601String(),
                    'category'          => $correspondence->category?->name,
                    'recipient'         => $correspondence->recipient?->name,
                ];

                $sender = 'System';
            } elseif ($notification->id_tuition_payment) {
                $type = 'tuition';
                $payment = $notification->tuitionPayment;
                $fee = $payment?->tuitionFee;
                $periodName = $fee?->academicPeriod?->name ?? 'Semester';

                if ($payment?->verification_status === 'verified') {
                    $title = 'Pembayaran UKT Lunas ✅';
                    $message = "Pembayaran UKT {$periodName} telah diverifikasi. Status: LUNAS.";
                } elseif ($payment?->verification_status === 'rejected') {
                    $title = 'Pembayaran UKT Ditolak ❌';
                    $message = "Bukti pembayaran UKT {$periodName} ditolak. Alasan: " . ($payment->rejection_reason ?? 'Tidak valid');
                } else {
                    $title = 'Update Pembayaran UKT';
                    $message = "Ada pembaruan status pembayaran UKT {$periodName}.";
                }

                $metadata = [
                    'id_tuition_payment' => (int) $notification->id_tuition_payment,
                    'id_tuition_fee' => $fee ? (int) $fee->id_tuition_fee : null,
                    'verification_status' => $payment?->verification_status,
                    'academic_period' => $periodName,
                ];

                $sender = 'System';
            } elseif ($notification->id_krs) {
                $type = 'krs';
                $krsEntry = $notification->krs;

                $subjectName = $krsEntry->subject->name_subject ?? '-';
                $classCode   = $krsEntry->krsClass->code_class ?? '-';

                if ($krsEntry->status === 'approved') {
                    $title   = 'KRS Disetujui';
                    $message = "Pengajuan KRS Anda untuk mata kuliah {$subjectName} (kelas {$classCode}) telah disetujui. Anda sudah terdaftar di kelas.";
                } elseif ($krsEntry->status === 'rejected') {
                    $title   = 'KRS Ditolak';
                    $reason  = $krsEntry->rejection_reason ?? '-';
                    $message = "Pengajuan KRS Anda untuk mata kuliah {$subjectName} (kelas {$classCode}) ditolak. Alasan: {$reason}";
                } else {
                    $title   = 'Update KRS';
                    $message = "Status KRS Anda untuk mata kuliah {$subjectName} (kelas {$classCode}): {$krsEntry->status}.";
                }

                $metadata = [
                    'id_krs'           => (int) $notification->id_krs,
                    'status'           => $krsEntry->status,
                    'subject_name'     => $subjectName,
                    'code_subject'     => $krsEntry->subject->code_subject ?? null,
                    'class_code'       => $classCode,
                    'rejection_reason' => $krsEntry->rejection_reason,
                    'processed_at'     => $krsEntry->processed_at?->toIso8601String(),
                ];

                $sender = 'System';
            }

            return [
                'id_notification' => (int)$notification->id_notification,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'sender' => $sender,
                'sent_at' => $notification->sent_at,
                'read_at' => $notification->read_at,
                'is_read' => (bool)($notification->read_at !== null),
                'metadata' => $metadata,
            ];
        });

        // Menghitung unread
        $unreadCount = Notification::where('id_user_si', $user->id_user_si)
            ->unread()
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi berhasil diambil.',
            'data' => [
                'notifications' => $formattedNotifications,
                'unread_count' => (int)$unreadCount,
                'total' => (int)$notifications->count(),
            ],
        ], 200);
    }

    /**
     * Get unread notifications count
     * GET /api/notifications/unread-count
     */
    public function getUnreadCount()
    {
        $user = Auth::user();

        $unreadCount = Notification::where('id_user_si', $user->id_user_si)
            ->unread()
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => ['unread_count' => (int)$unreadCount],
        ], 200);
    }

    /**
     * Mark notification as read
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = Notification::where('id_notification', $id)
            ->where('id_user_si', $user->id_user_si)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi berhasil ditandai sebagai dibaca.',
            'data' => [
                'id_notification' => (int)$notification->id_notification,
                'read_at' => $notification->read_at,
            ],
        ], 200);
    }

    /**
     * Mark all notifications as read
     * PUT /api/notifications/read-all
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        $updated = Notification::where('id_user_si', $user->id_user_si)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Semua notifikasi berhasil ditandai sebagai dibaca.',
            'data' => ['updated_count' => (int)$updated],
        ], 200);
    }

    /**
     * Delete notification
     * DELETE /api/notifications/{id}
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = Notification::where('id_notification', $id)
            ->where('id_user_si', $user->id_user_si)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi berhasil dihapus.',
        ], 200);
    }

    /**
     * Create announcement for admin, manager and lecturer
     * POST /api/announcements
     */
    public function createAnnouncement(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'id_class' => 'nullable|exists:classes,id_class',
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        // Role-based authorization
        if (!empty($validated['id_class'])) {
            // Announcement kelas - hanya dosen
            if ($user->role !== 'dosen') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya dosen yang dapat membuat pengumuman kelas.',
                ], 403);
            }

            $isTeaching = DB::table('lecturer_class')
                ->where('id_class', $validated['id_class'])
                ->where('id_user_si', $user->id_user_si)
                ->exists();

            if (!$isTeaching) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak mengajar di kelas ini.',
                ], 403);
            }
        } else {
            if (!in_array($user->role, ['admin', 'manager'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya admin dan manager yang dapat membuat pengumuman broadcast.',
                ], 403);
            }
        }

        DB::beginTransaction();

        // Auto-generate title jika kosong
        if (empty($validated['title']) && !empty($validated['id_class'])) {
            // Get class code untuk auto-title
            $classCode = DB::table('classes')
                ->where('id_class', $validated['id_class'])
                ->value('code_class');

            $validated['title'] = 'Pengumuman Kelas - ' . $classCode;
        } elseif (empty($validated['title'])) {
            $validated['title'] = 'Pengumuman Umum';
        }

        $announcement = Announcement::create($validated);

        // Menentukan penerimanya siapa aja
        if (!empty($validated['id_class'])) {
            // Class announcement - HANYA KE STUDENTS di kelas tersebut
            $recipients = DB::table('student_class')
                ->where('id_class', $validated['id_class'])
                ->pluck('id_user_si')
                ->unique();
        } else {
            $recipients = DB::table('users_si')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->orWhere('role', '=', 'mahasiswa')
                            ->orWhere('role', '=', 'dosen');
                })
                ->pluck('id_user_si')
                ->unique();
        }

        Log::info('Broadcasting notifications to ' . count($recipients) . ' recipients');

        try {
            foreach ($recipients as $recipientId) {
                $notif = Notification::create([
                    'id_user_si' => $recipientId,
                    'id_announcement' => $announcement->id_announcement,
                    'sent_at' => now(),
                ]);

                // Build metadata with full details (sama seperti API /notifications)
                $metadata = [
                    'id_announcement' => (int)$announcement->id_announcement,
                    'id_class' => $announcement->id_class ? (int)$announcement->id_class : null,
                ];

                // Jika pengumuman untuk kelas tertentu, tambahkan detail kelas
                if ($announcement->id_class) {
                    $classInfo = DB::table('classes')
                        ->select('classes.*', 'subjects.name_subject', 'subjects.code_subject')
                        ->leftJoin('subjects', 'classes.id_subject', '=', 'subjects.id_subject')
                        ->where('classes.id_class', $announcement->id_class)
                        ->first();

                    if ($classInfo) {
                        $metadata['class_code'] = $classInfo->code_class;
                        $metadata['subject_name'] = $classInfo->name_subject;
                        $metadata['subject_code'] = $classInfo->code_subject;
                        $metadata['id_subject'] = $classInfo->id_subject ? (int)$classInfo->id_subject : null;

                        // Get lecturer info
                        $lecturer = DB::table('lecturer_class')
                            ->select('staff_profiles.full_name')
                            ->join('staff_profiles', 'lecturer_class.id_user_si', '=', 'staff_profiles.id_user_si')
                            ->where('lecturer_class.id_class', $classInfo->id_class)
                            ->first();

                        if ($lecturer) {
                            $metadata['lecturer_name'] = $lecturer->full_name;
                        }

                        // Get student info (untuk recipient yang mahasiswa)
                        $studentInfo = DB::table('student_profiles')
                            ->where('id_user_si', $recipientId)
                            ->first();

                        if ($studentInfo) {
                            $metadata['student_name'] = $studentInfo->full_name;
                            $metadata['student_nim'] = $studentInfo->registration_number;
                        }
                    }
                }

                $notificationData = [
                    'id_notification' => (int)$notif->id_notification,
                    'type' => 'announcement',
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'sender' => 'System',
                    'sent_at' => $notif->sent_at->toIso8601String(),
                    'read_at' => null,
                    'is_read' => false,
                    'metadata' => $metadata,
                ];

                // Broadcast event NewNotification (WebSocket untuk user yang online)
                broadcast(new NewNotification($recipientId, $notificationData));

                Log::debug('Broadcasted notification to user: ' . $recipientId, [
                    'notif_id' => $notif->id_notification,
                    'metadata' => $metadata
                ]);

                // Send push notification (untuk user yang offline)
                $this->pushService->sendAnnouncementNotification(
                    $recipientId,
                    $announcement->title,
                    $announcement->message,
                    $announcement->id_announcement,
                    $announcement->id_class
                );
            }

            Log::info('All notifications broadcasted successfully');

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengumuman berhasil dibuat dan dikirim.',
                'data' => [
                    'id_announcement' => (int)$announcement->id_announcement,
                    'recipients_count' => (int)count($recipients),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all announcements for admin, manager, and lecturer
     * GET /api/announcements
     */
    public function getAnnouncements()
    {
        $user = Auth::user();

        $query = Announcement::with('class:id_class,code_class')
            ->orderBy('created_at', 'desc');

        if ($user->role === 'dosen') {
            $teachingClassIds = DB::table('lecturer_class')
                ->where('id_user_si', $user->id_user_si)
                ->pluck('id_class');

            $query->whereIn('id_class', $teachingClassIds);
        }

        $announcements = $query->get();

        $formattedAnnouncements = $announcements->map(function ($announcement) {
            return [
                'id_announcement' => (int)$announcement->id_announcement,
                'id_class' => $announcement->id_class ? (int)$announcement->id_class : null,
                'class_code' => $announcement->class ? $announcement->class->code_class : null,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengumuman berhasil diambil.',
            'data' => $formattedAnnouncements,
        ], 200);
    }
}
