<?php

namespace App\Http\Controllers;

use App\Events\NewChatMessage;
use App\Events\MessageRead;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\User_si;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\NewNotification;
use App\Services\PushNotificationService;

class ChatController extends Controller
{
    protected $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Mengambil daftar semua percakapan (PRIVATE ONLY - group chat removed).
     */
    public function indexConversations()
    {
        /** @var User_si $user */
        $user = Auth::user();

        // Ambil semua percakapan PRIVATE yang diikuti user
        $conversations = $user->chatConversations()
            ->where('chat_conversations.type', 'private')
            ->with(['participants:id_user_si,name,email']) // Load participants info
            ->get()
            ->map(function ($conversation) {
                return [
                    'id_conversation' => (int)$conversation->id_conversation,
                    'type' => $conversation->type,
                    'id_class' => $conversation->id_class ? (int)$conversation->id_class : null,
                    'id_initiator' => (int)$conversation->id_initiator,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'participants' => $conversation->participants->map(function ($participant) {
                        return [
                            'id_user_si' => (int)$participant->id_user_si,
                            'name' => $participant->name,
                            'email' => $participant->email,
                        ];
                    }),
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar percakapan berhasil diambil.',
            'data' => $conversations
        ]);
    }

    /**
     * Mencari atau membuat percakapan PRIVAT baru antara dua pengguna.
     */
    public function findOrCreatePrivateConversation(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users_si,id_user_si',
        ], [
            'recipient_id.required' => 'ID penerima harus diisi.',
            'recipient_id.exists' => 'Penerima tidak ditemukan.',
        ]);

        $recipientId = $validated['recipient_id'];
        /** @var User_si $user */
        $user = Auth::user();

        // Jangan izinkan pengguna membuat percakapan dengan diri sendiri
        if ($user->id_user_si == $recipientId) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak bisa membuat percakapan dengan diri sendiri.'
            ], 422);
        }

        // Cari percakapan privat yang sudah ada antara kedua pengguna.
        // Query ini memeriksa percakapan privat di mana user saat ini adalah partisipan,
        // dan juga memiliki partisipan lain dengan ID yang diminta.
        $conversation = $user->chatConversations()
            ->where('chat_conversations.type', 'private')
            ->whereHas('participants', function ($query) use ($recipientId) {
                $query->where('users_si.id_user_si', $recipientId);
            })
            ->first();

        // Jika percakapan sudah ada, langsung kembalikan.
        if ($conversation) {
            $conversation->load(['participants:id_user_si,name,email']);

            return response()->json([
                'status' => 'success',
                'message' => 'Percakapan sudah ada.',
                'data' => [
                    'id_conversation' => (int)$conversation->id_conversation,
                    'type' => $conversation->type,
                    'id_class' => $conversation->id_class ? (int)$conversation->id_class : null,
                    'id_initiator' => (int)$conversation->id_initiator,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                    'participants' => $conversation->participants->map(function ($participant) {
                        return [
                            'id_user_si' => (int)$participant->id_user_si,
                            'name' => $participant->name,
                            'email' => $participant->email,
                        ];
                    }),
                ]
            ]);
        }

        // Jika tidak ada, buat percakapan baru.
        $newConversation = ChatConversation::create([
            'type' => 'private',
            'id_initiator' => $user->id_user_si,
            // id_class akan null karena ini bukan chat grup kelas
        ]);

        // Tambahkan kedua pengguna (diri sendiri dan penerima) sebagai partisipan.
        $newConversation->participants()->attach([$user->id_user_si, $recipientId]);
        $newConversation->load(['participants:id_user_si,name,email']);

        return response()->json([
            'status' => 'success',
            'message' => 'Percakapan berhasil dibuat.',
            'data' => [
                'id_conversation' => (int)$newConversation->id_conversation,
                'type' => $newConversation->type,
                'id_class' => $newConversation->id_class ? (int)$newConversation->id_class : null,
                'id_initiator' => (int)$newConversation->id_initiator,
                'created_at' => $newConversation->created_at,
                'updated_at' => $newConversation->updated_at,
                'participants' => $newConversation->participants->map(function ($participant) {
                    return [
                        'id_user_si' => (int)$participant->id_user_si,
                        'name' => $participant->name,
                        'email' => $participant->email,
                    ];
                }),
            ]
        ], 201);
    }

    /**
     * Menampilkan semua pesan dari satu percakapan spesifik.
     */
    public function showMessages($conversationId)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->chatConversations()->where('chat_conversations.id_conversation', $conversationId)->exists()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $messages = ChatMessage::where('id_conversation', $conversationId)
            ->with(['sender:id_user_si,name'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) use ($user) {
                $isMe = $message->id_user_si === $user->id_user_si;
                return [
                    'id_message' => (int)$message->id_message,
                    'id_conversation' => (int)$message->id_conversation,
                    'id_user_si' => (int)$message->id_user_si,
                    'message' => $message->message,
                    'sent_at' => $message->created_at,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                    'sender' => $message->sender,
                    'read_status' => [
                        'read_by_count' => (int)($message->read_at ? 1 : 0),
                    ],
                ];
            });

        // Get conversation info dengan participants untuk chat redirect
        $conversation = ChatConversation::with([
            'participants:id_user_si,name',
            'participants.profile:id_profile,id_user_si,registration_number'
        ])->findOrFail($conversationId);

        // Get other participant info (untuk private chat)
        $otherParticipant = $conversation->participants
            ->where('id_user_si', '!=', $user->id_user_si)
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Pesan berhasil diambil.',
            'data' => [
                'messages' => $messages,
                'conversation' => [
                    'id_conversation' => (int)$conversation->id_conversation,
                    'type' => $conversation->type,
                    'other_participant' => $otherParticipant ? [
                        'id_user_si' => (int)$otherParticipant->id_user_si,
                        'name' => $otherParticipant->name,
                        'nim' => $otherParticipant->profile->registration_number ?? null,
                    ] : null,
                ],
            ]
        ], 200);
    }

    /**
     * Menyimpan pesan baru ke dalam sebuah percakapan dan menyiarkannya.
     */
    public function storeMessage(Request $request, $conversationId)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->chatConversations()->where('chat_conversations.id_conversation', $conversationId)->exists()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            $message = ChatMessage::create([
                'id_conversation' => $conversationId,
                'id_user_si' => $user->id_user_si,
                'message' => $validated['message'],
            ]);

            $message->load('sender:id_user_si,name');

            // Get conversation untuk cek tipe dan participant
            $conversation = ChatConversation::with('participants:id_user_si')
                ->findOrFail($conversationId);

            // Buat notifikasi untuk semua participant KECUALI sender
            // Get conversation untuk cek tipe dan participant
            $conversation = ChatConversation::with('participants:id_user_si')
                ->findOrFail($conversationId);

            $recipients = $conversation->participants
                ->where('id_user_si', '!=', $user->id_user_si);

            foreach ($recipients as $recipient) {
                // Simpan notifikasi ke database
                $notifModel = Notification::create([
                    'id_user_si' => $recipient->id_user_si,
                    'id_conversation' => $conversationId,
                    'id_message' => $message->id_message,
                    'sent_at' => now(),
                ]);

                // Data notifikasi untuk broadcast
                $notificationData = [
                    'id_notification' => (int)$notifModel->id_notification,
                    'type' => 'chat',
                    'title' => 'Pesan dari ' . $user->name,
                    'message' => $validated['message'],
                    'sender' => $user->name,
                    'isRead' => (bool)false,
                    'sentAt' => now()->toIso8601String(),
                    'metadata' => [
                        'id_conversation' => (int)$conversationId,
                        'id_message' => (int)$message->id_message,
                    ]
                ];

                // Broadcast event NewNotification (WebSocket untuk user yang online)
                broadcast(new NewNotification(
                    $recipient->id_user_si,
                    $notificationData
                ));

                // Send push notification (untuk user yang offline)
                $this->pushService->sendChatNotification(
                    $recipient->id_user_si,
                    $user->name,
                    $validated['message'],
                    $conversationId,
                    $message->id_message
                );
            }

            DB::commit();

            broadcast(new NewChatMessage($message))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil dikirim.',
                'data' => $message
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark messages as read by current user
     */
    public function markAsRead(Request $request, $conversationId)
    {
        /** @var User_si $user */
        $user = Auth::user();

        if (!$user->chatConversations()->where('chat_conversations.id_conversation', $conversationId)->exists()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $validated = $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:chat_messages,id_message',
        ]);

        DB::beginTransaction();

        try {
            $messageIds = $validated['message_ids'];

            // Filter messages yang belum dibaca dan bukan dari user sendiri
            $messages = ChatMessage::whereIn('id_message', $messageIds)
                ->where('id_conversation', $conversationId)
                ->where('id_user_si', '!=', $user->id_user_si) // Jangan mark pesan sendiri
                ->whereNull('read_at') // Hanya yang belum dibaca
                ->get();

            $markedMessages = [];

            foreach ($messages as $message) {
                // Mark as read dengan update read_at timestamp
                $message->update(['read_at' => now()]);

                $markedMessages[] = $message->id_message;

                // Hapus notifikasi chat yang terkait dengan message ini
                Notification::where('id_user_si', $user->id_user_si)
                    ->where('id_conversation', $conversationId)
                    ->where('id_message', $message->id_message)
                    ->delete();

                // Broadcast event MessageRead
                broadcast(new MessageRead(
                    $message->id_message,
                    $conversationId,
                    $user->id_user_si
                ))->toOthers();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil ditandai sebagai dibaca.',
                'data' => [
                    'marked_count' => (int)count($markedMessages),
                    'marked_message_ids' => array_map('intval', $markedMessages),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getContactList()
    {
        /** @var User_si $user */
        $user = Auth::user();

        // Ambil semua kelas yang diikuti pengguna (baik sebagai mahasiswa maupun dosen)
        // Eager load relasi yang dibutuhkan untuk efisiensi
        $userWithClasses = $user->load([
            'classes.lecturers.profile',
            'classes.lecturers.program',
            'classes.students.profile',
            'classes.students.program',
            'teachingClasses.lecturers.profile',
            'teachingClasses.lecturers.program',
            'teachingClasses.students.profile',
            'teachingClasses.students.program',
        ]);

        $allClasses = $userWithClasses->classes->merge($userWithClasses->teachingClasses);

        $lecturers = collect();
        $classmates = collect();

        // Kumpulkan semua dosen dan teman sekelas dari setiap kelas
        foreach ($allClasses as $class) {
            $lecturers = $lecturers->merge($class->lecturers);
            $classmates = $classmates->merge($class->students);
        }

        // Buat daftar unik dan hapus pengguna itu sendiri dari daftar
        $uniqueLecturers = $lecturers->unique('id_user_si')
            ->where('id_user_si', '!=', $user->id_user_si)
            ->values()
            ->map(function ($lecturer) {
                return [
                    'id_user_si' => (int)$lecturer->id_user_si,
                    'name' => $lecturer->name,
                    'username' => $lecturer->username,
                    'email' => $lecturer->email,
                    'role' => $lecturer->role,
                    'is_active' => (bool)$lecturer->is_active,
                    'id_program' => $lecturer->id_program ? (int)$lecturer->id_program : null,
                    'profile_image' => $lecturer->profile_image,
                    'profile' => $lecturer->profile ? [
                        'id_profile' => (int)$lecturer->profile->id_profile,
                        'registration_number' => $lecturer->profile->registration_number,
                    ] : null,
                    'program' => $lecturer->program ? [
                        'id_program' => (int)$lecturer->program->id_program,
                        'name' => $lecturer->program->name ?? null,
                    ] : null,
                ];
            });

        $uniqueClassmates = $classmates->unique('id_user_si')
            ->where('id_user_si', '!=', $user->id_user_si)
            ->values()
            ->map(function ($classmate) {
                return [
                    'id_user_si' => (int)$classmate->id_user_si,
                    'name' => $classmate->name,
                    'username' => $classmate->username,
                    'email' => $classmate->email,
                    'role' => $classmate->role,
                    'is_active' => (bool)$classmate->is_active,
                    'id_program' => $classmate->id_program ? (int)$classmate->id_program : null,
                    'profile_image' => $classmate->profile_image,
                    'profile' => $classmate->profile ? [
                        'id_profile' => (int)$classmate->profile->id_profile,
                        'registration_number' => $classmate->profile->registration_number,
                    ] : null,
                    'program' => $classmate->program ? [
                        'id_program' => (int)$classmate->program->id_program,
                        'name' => $classmate->program->name ?? null,
                    ] : null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kontak berhasil diambil.',
            'data' => [
                'lecturers' => $uniqueLecturers,
                'classmates' => $uniqueClassmates,
            ]
        ]);
    }
}
