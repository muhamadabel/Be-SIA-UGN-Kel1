<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookOrder;
use App\Models\BookSuggestion;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Services\PushNotificationService;

class LibraryAdminController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * GET /api/admin/library/dashboard
     * Statistik perpustakaan
     */
    public function dashboard()
    {
        $totalBooks          = Book::count();
        $activeBooks         = Book::active()->count();
        $totalOrders         = BookOrder::count();
        $activeOrders        = BookOrder::whereIn('status', [BookOrder::STATUS_ORDERED, BookOrder::STATUS_BORROWED])->count();
        $pendingOrders       = BookOrder::byStatus(BookOrder::STATUS_ORDERED)->count();
        $borrowedOrders      = BookOrder::byStatus(BookOrder::STATUS_BORROWED)->count();
        $totalSuggestions    = BookSuggestion::count();
        $pendingSuggestions  = BookSuggestion::byStatus(BookSuggestion::STATUS_PENDING)->count();

        return response()->json([
            'status'  => 'success',
            'message' => 'Dashboard perpustakaan berhasil diambil.',
            'data'    => [
                'total_books'         => $totalBooks,
                'active_books'        => $activeBooks,
                'total_orders'        => $totalOrders,
                'active_orders'       => $activeOrders,
                'pending_orders'      => $pendingOrders,
                'borrowed_orders'     => $borrowedOrders,
                'total_suggestions'   => $totalSuggestions,
                'pending_suggestions' => $pendingSuggestions,
            ],
        ]);
    }

    // =========================================================================
    // MANAJEMEN KATEGORI BUKU
    // =========================================================================

    /**
     * GET /api/admin/library/categories
     * Daftar semua kategori buku
     */
    public function indexCategories()
    {
        $categories = BookCategory::withCount('books')->orderBy('name')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kategori buku berhasil diambil.',
            'data'    => $categories,
        ]);
    }

    /**
     * POST /api/admin/library/categories
     * Tambah kategori buku baru
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:book_categories,name',
            'slug'        => 'required|string|max:255|unique:book_categories,slug|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $category = BookCategory::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori buku berhasil dibuat.',
            'data'    => $category,
        ], 201);
    }

    /**
     * PUT /api/admin/library/categories/{id}
     * Update kategori buku
     */
    public function updateCategory(Request $request, $id)
    {
        $category = BookCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:book_categories,name,' . $id . ',id_book_category',
            'slug'        => 'sometimes|required|string|max:255|unique:book_categories,slug,' . $id . ',id_book_category|alpha_dash',
            'description' => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori buku berhasil diperbarui.',
            'data'    => $category->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/library/categories/{id}
     * Hapus kategori buku
     */
    public function destroyCategory($id)
    {
        $category = BookCategory::findOrFail($id);

        if ($category->books()->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki buku terkait.',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kategori buku berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // MANAJEMEN BUKU
    // =========================================================================

    /**
     * GET /api/admin/library/books
     * Daftar semua buku (termasuk inactive)
     */
    public function indexBooks(Request $request)
    {
        $query = Book::with(['category:id_book_category,name,slug'])
            ->orderBy('title', 'asc');

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter kategori
        if ($request->filled('id_book_category')) {
            $query->byCategory($request->id_book_category);
        }

        // Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $books = $query->paginate($perPage);

        $books->getCollection()->transform(fn ($book) => $this->formatBook($book));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar buku berhasil diambil.',
            'data'    => $books->items(),
            'meta'    => [
                'current_page' => $books->currentPage(),
                'last_page'    => $books->lastPage(),
                'per_page'     => $books->perPage(),
                'total'        => $books->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/library/books
     * Tambah buku baru
     */
    public function storeBook(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'author'           => 'required|string|max:255',
            'publisher'        => 'nullable|string|max:255',
            'year'             => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'isbn'             => 'nullable|string|max:20|unique:books,isbn',
            'id_book_category' => 'required|exists:book_categories,id_book_category',
            'total_stock'      => 'required|integer|min:0',
        ]);

        $validated['available_stock'] = $validated['total_stock'];
        $validated['status']          = Book::STATUS_ACTIVE;

        $book = Book::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Buku berhasil ditambahkan.',
            'data'    => $this->formatBook($book->load('category')),
        ], 201);
    }

    /**
     * GET /api/admin/library/books/{id}
     * Detail buku (dengan statistik peminjaman)
     */
    public function showBook($id)
    {
        $book = Book::with(['category'])->findOrFail($id);

        $bookData = $this->formatBook($book);
        $bookData['order_statistics'] = [
            'total_orders'    => $book->orders()->count(),
            'active_orders'   => $book->orders()->whereIn('status', [BookOrder::STATUS_ORDERED, BookOrder::STATUS_BORROWED])->count(),
            'completed_orders'=> $book->orders()->byStatus(BookOrder::STATUS_RETURNED)->count(),
        ];

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail buku berhasil diambil.',
            'data'    => $bookData,
        ]);
    }

    /**
     * PUT /api/admin/library/books/{id}
     * Update buku
     */
    public function updateBook(Request $request, $id)
    {
        $book = Book::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:255',
            'author'           => 'sometimes|required|string|max:255',
            'publisher'        => 'nullable|string|max:255',
            'year'             => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'isbn'             => 'nullable|string|max:20|unique:books,isbn,' . $id . ',id_book',
            'id_book_category' => 'sometimes|required|exists:book_categories,id_book_category',
            'total_stock'      => 'sometimes|required|integer|min:0',
        ]);

        // Jika total_stock berubah, sesuaikan available_stock
        if (isset($validated['total_stock'])) {
            // Validasi: total_stock tidak boleh kurang dari jumlah pesanan aktif
            $activeOrders = $book->orders()
                ->whereIn('status', [BookOrder::STATUS_ORDERED, BookOrder::STATUS_BORROWED])
                ->count();

            if ($validated['total_stock'] < $activeOrders) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Total stok tidak boleh kurang dari jumlah pesanan aktif saat ini ({$activeOrders} pesanan).",
                ], 422);
            }

            $stockDiff    = $validated['total_stock'] - $book->total_stock;
            $newAvailable = $book->available_stock + $stockDiff;
            $validated['available_stock'] = max(0, $newAvailable);
        }

        $book->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Buku berhasil diperbarui.',
            'data'    => $this->formatBook($book->fresh()->load('category')),
        ]);
    }

    /**
     * PATCH /api/admin/library/books/{id}/toggle-status
     * Aktifkan/nonaktifkan buku
     */
    public function toggleBookStatus($id)
    {
        $book = Book::findOrFail($id);

        $book->update([
            'status' => $book->status === Book::STATUS_ACTIVE
                ? Book::STATUS_INACTIVE
                : Book::STATUS_ACTIVE,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Status buku berhasil diperbarui.',
            'data'    => [
                'id_book'    => (int) $book->id_book,
                'title'      => $book->title,
                'new_status' => $book->status,
            ],
        ]);
    }

    /**
     * DELETE /api/admin/library/books/{id}
     * Hapus buku (hanya jika tidak ada pesanan aktif)
     */
    public function destroyBook($id)
    {
        $book = Book::findOrFail($id);

        // Cegah hapus jika ada pesanan aktif (ordered / borrowed)
        $activeOrders = $book->orders()
            ->whereIn('status', [BookOrder::STATUS_ORDERED, BookOrder::STATUS_BORROWED])
            ->count();

        if ($activeOrders > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => "Buku tidak dapat dihapus karena masih ada {$activeOrders} pesanan aktif (ordered/borrowed).",
            ], 409);
        }

        $book->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Buku berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // MANAJEMEN PEMESANAN / PEMINJAMAN
    // =========================================================================

    /**
     * GET /api/admin/library/orders
     * Daftar semua pesanan/peminjaman
     */
    public function indexOrders(Request $request)
    {
        $query = BookOrder::with(['user:id_user_si,name,email', 'book.category'])
            ->orderBy('created_at', 'desc');

        // Filter status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Pencarian berdasarkan nama user atau judul buku
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('book', fn ($b) => $b->where('title', 'like', "%{$search}%"));
            });
        }

        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        $orders->getCollection()->transform(fn ($order) => $this->formatOrder($order));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar pesanan berhasil diambil.',
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/library/orders/{id}
     * Detail pesanan
     */
    public function showOrder($id)
    {
        $order = BookOrder::with(['user:id_user_si,name,email', 'book.category'])->findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail pesanan berhasil diambil.',
            'data'    => $this->formatOrder($order),
        ]);
    }

    /**
     * PATCH /api/admin/library/orders/{id}/confirm-borrow
     * Admin mengkonfirmasi peminjaman (status: ordered → borrowed)
     */
    public function confirmBorrow(Request $request, $id)
    {
        $order = BookOrder::with(['user', 'book'])->findOrFail($id);

        if ($order->status !== BookOrder::STATUS_ORDERED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan ini tidak dapat dikonfirmasi karena statusnya bukan "ordered".',
            ], 422);
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'status'      => BookOrder::STATUS_BORROWED,
                'borrowed_at' => now(),
                'admin_note'  => $validated['admin_note'] ?? $order->admin_note,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal mengkonfirmasi peminjaman', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengkonfirmasi peminjaman.',
            ], 500);
        }

        $fresh = $order->fresh()->load(['user', 'book.category']);

        // Kirim notifikasi setelah response dikirim (non-blocking)
        dispatch(fn () => $this->sendOrderNotification(
            $fresh,
            'Peminjaman Dikonfirmasi',
            "Peminjaman buku \"{$fresh->book->title}\" telah dikonfirmasi. Silakan ambil buku di perpustakaan."
        ))->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Peminjaman berhasil dikonfirmasi.',
            'data'    => $this->formatOrder($fresh),
        ]);
    }

    /**
     * PATCH /api/admin/library/orders/{id}/confirm-return
     * Admin mengkonfirmasi pengembalian (status: borrowed → returned)
     */
    public function confirmReturn(Request $request, $id)
    {
        $order = BookOrder::with(['user', 'book'])->findOrFail($id);

        if ($order->status !== BookOrder::STATUS_BORROWED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan ini tidak dapat dikembalikan karena statusnya bukan "borrowed".',
            ], 422);
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'status'      => BookOrder::STATUS_RETURNED,
                'returned_at' => now(),
                'admin_note'  => $validated['admin_note'] ?? $order->admin_note,
            ]);

            // Kembalikan stok
            $order->book->increment('available_stock');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal mengkonfirmasi pengembalian', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengkonfirmasi pengembalian.',
            ], 500);
        }

        $fresh = $order->fresh()->load(['user', 'book.category']);

        // Kirim notifikasi setelah response dikirim (non-blocking)
        dispatch(fn () => $this->sendOrderNotification(
            $fresh,
            'Buku Telah Dikembalikan',
            "Pengembalian buku \"{$fresh->book->title}\" telah dikonfirmasi. Terima kasih!"
        ))->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengembalian berhasil dikonfirmasi.',
            'data'    => $this->formatOrder($fresh),
        ]);
    }

    /**
     * PATCH /api/admin/library/orders/{id}/cancel
     * Admin membatalkan pesanan (status: ordered → cancelled)
     * Mengembalikan stok buku dan mengirim notifikasi ke user
     */
    public function adminCancelOrder(Request $request, $id)
    {
        $order = BookOrder::with(['user', 'book'])->findOrFail($id);

        if ($order->status !== BookOrder::STATUS_ORDERED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan ini tidak dapat dibatalkan. Hanya pesanan dengan status "ordered" yang dapat dibatalkan oleh admin.',
            ], 422);
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'status'     => BookOrder::STATUS_CANCELLED,
                'admin_note' => $validated['admin_note'] ?? $order->admin_note,
            ]);

            // Kembalikan stok yang telah dikurangi saat user memesan
            $order->book->increment('available_stock');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal membatalkan pesanan oleh admin', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membatalkan pesanan.',
            ], 500);
        }

        $fresh = $order->fresh()->load(['user', 'book.category']);

        // Kirim notifikasi ke user setelah response dikirim (non-blocking)
        dispatch(fn () => $this->sendOrderNotification(
            $fresh,
            'Pesanan Dibatalkan oleh Admin',
            "Pesanan buku \"{$fresh->book->title}\" telah dibatalkan oleh pengelola perpustakaan." .
            ($validated['admin_note'] ? ' Catatan: ' . $validated['admin_note'] : '')
        ))->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pesanan berhasil dibatalkan.',
            'data'    => $this->formatOrder($fresh),
        ]);
    }

    // =========================================================================
    // MANAJEMEN USULAN BUKU
    // =========================================================================

    /**
     * GET /api/admin/library/suggestions
     * Daftar semua usulan buku
     */
    public function indexSuggestions(Request $request)
    {
        $query = BookSuggestion::with(['user:id_user_si,name,email'])
            ->orderBy('created_at', 'desc');

        // Filter status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $suggestions = $query->get()->map(fn ($s) => $this->formatSuggestion($s));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar usulan buku berhasil diambil.',
            'data'    => $suggestions,
        ]);
    }

    /**
     * GET /api/admin/library/suggestions/{id}
     * Detail usulan buku
     */
    public function showSuggestion($id)
    {
        $suggestion = BookSuggestion::with(['user:id_user_si,name,email'])->findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail usulan buku berhasil diambil.',
            'data'    => $this->formatSuggestion($suggestion),
        ]);
    }

    /**
     * PATCH /api/admin/library/suggestions/{id}/respond
     * Respon usulan buku (approve/reject)
     */
    public function respondSuggestion(Request $request, $id)
    {
        $validated = $request->validate([
            'status'         => 'required|in:approved,rejected',
            'admin_response' => 'required|string|max:1000',
        ]);

        $suggestion = BookSuggestion::with('user')->findOrFail($id);

        if ($suggestion->status !== BookSuggestion::STATUS_PENDING) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Usulan ini sudah direspons sebelumnya.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $suggestion->update([
                'status'         => $validated['status'],
                'admin_response' => $validated['admin_response'],
                'responded_at'   => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal merespons usulan buku', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat merespons usulan.',
            ], 500);
        }

        $fresh = $suggestion->fresh()->load('user');

        $statusLabel = $validated['status'] === 'approved' ? 'Disetujui' : 'Ditolak';

        // Kirim notifikasi setelah response dikirim (non-blocking)
        dispatch(fn () => $this->sendSuggestionNotification(
            $fresh,
            "Usulan Buku {$statusLabel}",
            "Usulan buku \"{$fresh->title}\" telah {$statusLabel} oleh pengelola perpustakaan."
        ))->afterResponse();

        return response()->json([
            'status'  => 'success',
            'message' => 'Respons usulan berhasil dikirim.',
            'data'    => $this->formatSuggestion($fresh),
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Format data buku untuk response JSON
     */
    private function formatBook(Book $book): array
    {
        return [
            'id_book'          => (int) $book->id_book,
            'title'            => $book->title,
            'author'           => $book->author,
            'publisher'        => $book->publisher,
            'year'             => $book->year,
            'isbn'             => $book->isbn,
            'category'         => $book->category ? [
                'id_book_category' => (int) $book->category->id_book_category,
                'name'             => $book->category->name,
                'slug'             => $book->category->slug,
            ] : null,
            'total_stock'      => $book->total_stock,
            'available_stock'  => $book->available_stock,
            'is_available'     => $book->isAvailable(),
            'status'           => $book->status,
            'created_at'       => $book->created_at,
            'updated_at'       => $book->updated_at,
        ];
    }

    /**
     * Format data pesanan untuk response JSON
     */
    private function formatOrder(BookOrder $order): array
    {
        return [
            'id_book_order'        => (int) $order->id_book_order,
            'id_user'              => (int) $order->id_user,
            'user_name'            => $order->user?->name,
            'user_email'           => $order->user?->email,
            'book'                 => $order->book ? [
                'id_book'  => (int) $order->book->id_book,
                'title'    => $order->book->title,
                'author'   => $order->book->author,
                'category' => $order->book->category?->name,
            ] : null,
            'status'               => $order->status,
            'ordered_at'           => $order->ordered_at,
            'borrowed_at'          => $order->borrowed_at,
            'returned_at'          => $order->returned_at,
            'borrow_duration_days' => $order->borrow_duration_days,
            'borrow_duration'      => $order->borrow_duration,
            'admin_note'           => $order->admin_note,
            'created_at'           => $order->created_at,
            'updated_at'           => $order->updated_at,
        ];
    }

    /**
     * Format data usulan buku untuk response JSON
     */
    private function formatSuggestion(BookSuggestion $s): array
    {
        return [
            'id_book_suggestion' => (int) $s->id_book_suggestion,
            'id_user'            => (int) $s->id_user,
            'user_name'          => $s->user?->name,
            'user_email'         => $s->user?->email,
            'title'              => $s->title,
            'author'             => $s->author,
            'reason'             => $s->reason,
            'status'             => $s->status,
            'admin_response'     => $s->admin_response,
            'responded_at'       => $s->responded_at,
            'created_at'         => $s->created_at,
            'updated_at'         => $s->updated_at,
        ];
    }

    /**
     * Kirim notifikasi terkait pemesanan buku
     */
    private function sendOrderNotification(BookOrder $order, string $title, string $message): void
    {
        $recipientUserId = $order->id_user;

        try {
            $notif = Notification::create([
                'id_user_si'    => $recipientUserId,
                'id_book_order' => $order->id_book_order,
                'sent_at'       => now(),
            ]);

            $metadata = [
                'id_book_order' => (int) $order->id_book_order,
                'id_book'       => (int) $order->id_book,
                'book_title'    => $order->book?->title,
                'status'        => $order->status,
            ];

            $notificationData = [
                'id_notification' => (int) $notif->id_notification,
                'type'            => 'library_order',
                'title'           => $title,
                'message'         => $message,
                'sender'          => 'System',
                'sent_at'         => $notif->sent_at->toIso8601String(),
                'read_at'         => null,
                'is_read'         => false,
                'metadata'        => $metadata,
            ];

            broadcast(new NewNotification($recipientUserId, $notificationData));

            $this->pushService->sendToUser(
                $recipientUserId,
                $title,
                $message,
                array_merge($metadata, ['type' => 'library_order', 'screen' => 'LibraryActivity'])
            );

            Log::info('Library order notification sent', [
                'id_book_order'    => $order->id_book_order,
                'recipient_user_id'=> $recipientUserId,
                'status'           => $order->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi pesanan buku', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Kirim notifikasi terkait usulan buku
     */
    private function sendSuggestionNotification(BookSuggestion $suggestion, string $title, string $message): void
    {
        $recipientUserId = $suggestion->id_user;

        try {
            $notif = Notification::create([
                'id_user_si'         => $recipientUserId,
                'id_book_suggestion' => $suggestion->id_book_suggestion,
                'sent_at'            => now(),
            ]);

            $metadata = [
                'id_book_suggestion' => (int) $suggestion->id_book_suggestion,
                'suggestion_title'   => $suggestion->title,
                'status'             => $suggestion->status,
            ];

            $notificationData = [
                'id_notification' => (int) $notif->id_notification,
                'type'            => 'library_suggestion',
                'title'           => $title,
                'message'         => $message,
                'sender'          => 'System',
                'sent_at'         => $notif->sent_at->toIso8601String(),
                'read_at'         => null,
                'is_read'         => false,
                'metadata'        => $metadata,
            ];

            broadcast(new NewNotification($recipientUserId, $notificationData));

            $this->pushService->sendToUser(
                $recipientUserId,
                $title,
                $message,
                array_merge($metadata, ['type' => 'library_suggestion', 'screen' => 'LibrarySuggestion'])
            );

            Log::info('Library suggestion notification sent', [
                'id_book_suggestion' => $suggestion->id_book_suggestion,
                'recipient_user_id'  => $recipientUserId,
                'status'             => $suggestion->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi usulan buku', ['error' => $e->getMessage()]);
        }
    }
}
