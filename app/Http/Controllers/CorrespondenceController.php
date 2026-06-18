<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Correspondence;
use App\Models\CorrespondenceCategory;
use App\Models\CorrespondenceRecipient;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Services\PushNotificationService;

class CorrespondenceController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    // =========================================================================
    // CORRESPONDENCE CATEGORIES
    // =========================================================================

    /**
     * GET /api/correspondence/categories
     * Semua user bisa mengakses
     */
    public function indexCategories()
    {
        $categories = CorrespondenceCategory::orderBy('name')->get([
            'id_category', 'name', 'slug', 'description', 'created_at', 'updated_at',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kategori persuratan berhasil diambil.',
            'data'    => $categories,
        ]);
    }

    /**
     * GET /api/correspondence/categories/{id}
     */
    public function showCategory($id)
    {
        $category = CorrespondenceCategory::findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail kategori berhasil diambil.',
            'data'    => $category,
        ]);
    }

    /**
     * POST /api/correspondence/categories
     * Hanya admin & manager
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:correspondence_categories,name',
            'slug'        => 'required|string|max:255|unique:correspondence_categories,slug|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $category = CorrespondenceCategory::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori berhasil dibuat.',
            'data'    => $category,
        ], 201);
    }

    /**
     * PATCH /api/correspondence/categories/{id}
     * Hanya admin & manager
     */
    public function updateCategory(Request $request, $id)
    {
        $category = CorrespondenceCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:correspondence_categories,name,' . $id . ',id_category',
            'slug'        => 'sometimes|required|string|max:255|unique:correspondence_categories,slug,' . $id . ',id_category|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori berhasil diperbarui.',
            'data'    => $category->fresh(),
        ]);
    }

    /**
     * DELETE /api/correspondence/categories/{id}
     * Hanya admin & manager
     */
    public function destroyCategory($id)
    {
        $category = CorrespondenceCategory::findOrFail($id);

        if ($category->correspondences()->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki surat terkait.',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // CORRESPONDENCE RECIPIENTS
    // =========================================================================

    /**
     * GET /api/correspondence/recipients
     * Semua user bisa mengakses
     */
    public function indexRecipients()
    {
        $recipients = CorrespondenceRecipient::orderBy('name')->get([
            'id_recipient', 'name', 'slug', 'description', 'created_at', 'updated_at',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar penerima persuratan berhasil diambil.',
            'data'    => $recipients,
        ]);
    }

    /**
     * GET /api/correspondence/recipients/{id}
     */
    public function showRecipient($id)
    {
        $recipient = CorrespondenceRecipient::findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail penerima berhasil diambil.',
            'data'    => $recipient,
        ]);
    }

    /**
     * POST /api/correspondence/recipients
     * Hanya admin & manager
     */
    public function storeRecipient(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:correspondence_recipient,name',
            'slug'        => 'required|string|max:255|unique:correspondence_recipient,slug|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $recipient = CorrespondenceRecipient::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Penerima berhasil dibuat.',
            'data'    => $recipient,
        ], 201);
    }

    /**
     * PATCH /api/correspondence/recipients/{id}
     * Hanya admin & manager
     */
    public function updateRecipient(Request $request, $id)
    {
        $recipient = CorrespondenceRecipient::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:correspondence_recipient,name,' . $id . ',id_recipient',
            'slug'        => 'sometimes|required|string|max:255|unique:correspondence_recipient,slug,' . $id . ',id_recipient|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $recipient->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Penerima berhasil diperbarui.',
            'data'    => $recipient->fresh(),
        ]);
    }

    /**
     * DELETE /api/correspondence/recipients/{id}
     * Hanya admin & manager
     */
    public function destroyRecipient($id)
    {
        $recipient = CorrespondenceRecipient::findOrFail($id);

        if ($recipient->correspondences()->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Penerima tidak dapat dihapus karena masih memiliki surat terkait.',
            ], 409);
        }

        $recipient->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Penerima berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // CORRESPONDENCES (SURAT)
    // =========================================================================

    /**
     * GET /api/correspondence
     * - Mahasiswa/Dosen: hanya milik sendiri
     * - Admin/Manager: semua surat + filter by status/category/recipient
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Correspondence::with([
            'category:id_category,name,slug',
            'recipient:id_recipient,name,slug',
            'sender:id_user_si,name,email',
        ])->orderBy('created_at', 'desc');

        // Mahasiswa & dosen hanya boleh lihat surat milik sendiri
        if (in_array($user->role, ['mahasiswa', 'dosen'])) {
            $query->where('id_user', $user->id_user_si);
        }

        // Filter opsional (untuk semua role)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_category')) {
            $query->where('id_category', $request->id_category);
        }

        if ($request->filled('id_recipient')) {
            $query->where('id_recipient', $request->id_recipient);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('correspondence_body', 'like', "%{$search}%");
            });
        }

        $correspondences = $query->get()->map(fn ($c) => $this->formatCorrespondence($c));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar persuratan berhasil diambil.',
            'data'    => $correspondences,
        ]);
    }

    /**
     * GET /api/correspondence/{id}
     */
    public function show($id)
    {
        $user = Auth::user();

        $correspondence = Correspondence::with([
            'category:id_category,name,slug,description',
            'recipient:id_recipient,name,slug,description',
            'sender:id_user_si,name,email',
        ])->findOrFail($id);

        // Mahasiswa & dosen hanya boleh lihat milik sendiri
        if (in_array($user->role, ['mahasiswa', 'dosen']) && $correspondence->id_user !== $user->id_user_si) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki akses ke surat ini.',
            ], 403);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail persuratan berhasil diambil.',
            'data'    => $this->formatCorrespondence($correspondence),
        ]);
    }

    /**
     * POST /api/correspondence
     * Mahasiswa & dosen bisa membuat surat
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_category'        => 'required|exists:correspondence_categories,id_category',
            'id_recipient'       => 'required|exists:correspondence_recipient,id_recipient',
            'title'              => 'required|string|max:255',
            'correspondence_body'=> 'required|string',
            'attachment'         => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file           = $request->file('attachment');
                $filename       = time() . '_' . $user->id_user_si . '.' . $file->getClientOriginalExtension();
                $attachmentPath = $file->storeAs('correspondences/attachments', $filename, 'public');
            }

            $correspondence = Correspondence::create([
                'id_user'             => $user->id_user_si,
                'id_category'         => $validated['id_category'],
                'id_recipient'        => $validated['id_recipient'],
                'title'               => $validated['title'],
                'correspondence_body' => $validated['correspondence_body'],
                'status'              => Correspondence::STATUS_SUBMITTED,
                'attachment'          => $attachmentPath,
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Surat berhasil dikirim.',
                'data'    => $this->formatCorrespondence($correspondence->load(['category', 'recipient', 'sender'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menyimpan surat', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan surat.',
            ], 500);
        }
    }

    /**
     * PATCH /api/correspondence/{id}
     * Mahasiswa/Dosen hanya bisa edit surat sendiri dengan status "submitted"
     */
    public function update(Request $request, $id)
    {
        $user           = Auth::user();
        $correspondence = Correspondence::findOrFail($id);

        // Hanya pemilik yang bisa edit
        if ($correspondence->id_user !== $user->id_user_si) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki izin untuk mengubah surat ini.',
            ], 403);
        }

        // Hanya surat dengan status submitted yang bisa diedit
        if ($correspondence->status !== Correspondence::STATUS_SUBMITTED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Surat yang sudah diproses tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'id_category'        => 'sometimes|required|exists:correspondence_categories,id_category',
            'id_recipient'       => 'sometimes|required|exists:correspondence_recipient,id_recipient',
            'title'              => 'sometimes|required|string|max:255',
            'correspondence_body'=> 'sometimes|required|string',
            'attachment'         => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('attachment')) {
                // Hapus lampiran lama jika ada
                if ($correspondence->attachment) {
                    Storage::disk('public')->delete($correspondence->attachment);
                }
                $file           = $request->file('attachment');
                $filename       = time() . '_' . $user->id_user_si . '.' . $file->getClientOriginalExtension();
                $validated['attachment'] = $file->storeAs('correspondences/attachments', $filename, 'public');
            }

            $correspondence->update($validated);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Surat berhasil diperbarui.',
                'data'    => $this->formatCorrespondence($correspondence->fresh()->load(['category', 'recipient', 'sender'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui surat', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui surat.',
            ], 500);
        }
    }

    /**
     * DELETE /api/correspondence/{id}
     * Pemilik hanya bisa hapus surat yang masih "submitted"
     * Admin/Manager bisa hapus surat apapun
     */
    public function destroy($id)
    {
        $user           = Auth::user();
        $correspondence = Correspondence::findOrFail($id);

        $isAdminOrManager = in_array($user->role, ['admin', 'manager']);

        if (!$isAdminOrManager) {
            if ($correspondence->id_user !== $user->id_user_si) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Anda tidak memiliki izin untuk menghapus surat ini.',
                ], 403);
            }

            if ($correspondence->status !== Correspondence::STATUS_SUBMITTED) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Surat yang sudah diproses tidak dapat dihapus.',
                ], 422);
            }
        }

        // Hapus file lampiran dari storage
        if ($correspondence->attachment) {
            Storage::disk('public')->delete($correspondence->attachment);
        }

        $correspondence->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Surat berhasil dihapus.',
        ]);
    }

    /**
     * DELETE /api/correspondence/{id}/attachment
     * Pemilik hapus lampiran surat yang masih "submitted"
     */
    public function deleteAttachment($id)
    {
        $user           = Auth::user();
        $correspondence = Correspondence::findOrFail($id);

        if ($correspondence->id_user !== $user->id_user_si) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki izin.',
            ], 403);
        }

        if ($correspondence->status !== Correspondence::STATUS_SUBMITTED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Surat yang sudah diproses tidak dapat diubah.',
            ], 422);
        }

        if (!$correspondence->attachment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Surat ini tidak memiliki lampiran.',
            ], 404);
        }

        Storage::disk('public')->delete($correspondence->attachment);
        $correspondence->update(['attachment' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Lampiran berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // ADMIN / MANAGER ACTIONS
    // =========================================================================

    /**
     * PATCH /api/correspondence/{id}/respond
     * Admin/Manager membalas surat + mengubah status + kirim notifikasi ke pengirim
     */
    public function respond(Request $request, $id)
    {
        $validated = $request->validate([
            'status'        => 'required|in:process,resolved,rejected',
            'response_text' => 'required|string',
        ]);

        $correspondence = Correspondence::with(['sender', 'category', 'recipient'])->findOrFail($id);

        $oldStatus = $correspondence->status;

        DB::beginTransaction();
        try {
            $correspondence->update([
                'status'        => $validated['status'],
                'response_text' => $validated['response_text'],
                'responded_at'  => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal merespons surat', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengirim respons.',
            ], 500);
        }

        $fresh = $correspondence->fresh()->load(['category', 'recipient', 'sender']);

        // Kirim notifikasi setelah response dikirim ke frontend (non-blocking)
        dispatch(fn() => $this->sendCorrespondenceNotification($fresh, $oldStatus))
            ->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Respons berhasil dikirim dan notifikasi telah diteruskan kepada pengirim.',
            'data'    => $this->formatCorrespondence($fresh),
        ]);
    }

    /**
     * PATCH /api/correspondence/{id}/status
     * Admin/Manager mengubah status surat saja (tanpa teks respons)
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,process,resolved,rejected',
        ]);

        $correspondence = Correspondence::with(['sender', 'category', 'recipient'])->findOrFail($id);
        $oldStatus = $correspondence->status;

        $correspondence->update(['status' => $validated['status']]);

        // Kirim notifikasi setelah response dikirim ke frontend (non-blocking)
        dispatch(fn() => $this->sendCorrespondenceNotification($correspondence->fresh(), $oldStatus))
            ->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Status surat berhasil diperbarui.',
            'data'    => [
                'id_correspondence' => (int) $correspondence->id_correspondence,
                'old_status'        => $oldStatus,
                'new_status'        => $correspondence->status,
            ],
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Format data correspondence untuk response JSON
     */
    private function formatCorrespondence(Correspondence $c): array
    {
        return [
            'id_correspondence'   => (int) $c->id_correspondence,
            'id_user'             => (int) $c->id_user,
            'sender_name'         => $c->sender?->name,
            'sender_email'        => $c->sender?->email,
            'category'            => $c->category ? [
                'id_category' => (int) $c->category->id_category,
                'name'        => $c->category->name,
                'slug'        => $c->category->slug,
            ] : null,
            'recipient'           => $c->recipient ? [
                'id_recipient' => (int) $c->recipient->id_recipient,
                'name'         => $c->recipient->name,
                'slug'         => $c->recipient->slug,
            ] : null,
            'title'               => $c->title,
            'correspondence_body' => $c->correspondence_body,
            'status'              => $c->status,
            'attachment_url'      => $c->attachment ? asset('storage/' . $c->attachment) : null,
            'response_text'       => $c->response_text,
            'responded_at'        => $c->responded_at,
            'created_at'          => $c->created_at,
            'updated_at'          => $c->updated_at,
        ];
    }

    /**
     * Kirim notifikasi ke pengirim surat setelah admin merespons / mengubah status
     */
    private function sendCorrespondenceNotification(Correspondence $correspondence, string $oldStatus): void
    {
        $recipientUserId = $correspondence->id_user;
        $newStatus       = $correspondence->status;

        $statusLabels = [
            'submitted' => 'Diterima',
            'process'   => 'Sedang Diproses',
            'resolved'  => 'Diselesaikan',
            'rejected'  => 'Ditolak',
        ];

        $title   = 'Update Status Surat: ' . $correspondence->title;
        $message = "Status surat Anda telah berubah dari \"{$statusLabels[$oldStatus]}\" menjadi \"{$statusLabels[$newStatus]}\".";

        if ($correspondence->response_text) {
            $message .= ' Pengelola telah memberikan respons terhadap surat Anda.';
        }

        try {
            // Buat record notifikasi
            $notif = Notification::create([
                'id_user_si'       => $recipientUserId,
                'id_correspondence'=> $correspondence->id_correspondence,
                'sent_at'          => now(),
            ]);

            $metadata = [
                'id_correspondence' => (int) $correspondence->id_correspondence,
                'title'             => $correspondence->title,
                'old_status'        => $oldStatus,
                'new_status'        => $newStatus,
                'responded_at'      => $correspondence->responded_at?->toIso8601String(),
            ];

            $notificationData = [
                'id_notification'   => (int) $notif->id_notification,
                'type'              => 'correspondence',
                'title'             => $title,
                'message'           => $message,
                'sender'            => 'System',
                'sent_at'           => $notif->sent_at->toIso8601String(),
                'read_at'           => null,
                'is_read'           => false,
                'metadata'          => $metadata,
            ];

            // Broadcast WebSocket (user yang online)
            broadcast(new NewNotification($recipientUserId, $notificationData));

            // Push notification (user yang offline)
            $this->pushService->sendToUser(
                $recipientUserId,
                $title,
                $message,
                array_merge($metadata, ['type' => 'correspondence', 'screen' => 'CorrespondenceDetail'])
            );

            Log::info('Correspondence notification sent', [
                'id_correspondence' => $correspondence->id_correspondence,
                'recipient_user_id' => $recipientUserId,
                'new_status'        => $newStatus,
            ]);
        } catch (\Exception $e) {
            // Notifikasi gagal tidak boleh membatalkan operasi utama
            Log::error('Gagal mengirim notifikasi correspondence', ['error' => $e->getMessage()]);
        }
    }
}
