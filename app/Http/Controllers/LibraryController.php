<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Book;
use App\Models\BookOrder;
use App\Models\BookSuggestion;
use App\Models\BookCategory;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Services\PushNotificationService;

class LibraryController extends Controller
{
    protected PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    // =========================================================================
    // KATALOG BUKU
    // =========================================================================

    /**
     * GET /api/library/books
     * Daftar buku perpustakaan dengan pencarian dan filter kategori
     */
    public function indexBooks(Request $request)
    {
        $query = Book::with(['category:id_book_category,name,slug'])
            ->active()
            ->orderBy('title', 'asc');

        // Filter berdasarkan kategori
        if ($request->filled('id_book_category')) {
            $query->byCategory($request->id_book_category);
        }

        // Pencarian berdasarkan judul atau penulis
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
     * GET /api/library/books/{id}
     * Detail buku
     */
    public function showBook($id)
    {
        $book = Book::with(['category:id_book_category,name,slug'])->findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail buku berhasil diambil.',
            'data'    => $this->formatBook($book),
        ]);
    }

    /**
     * GET /api/library/categories
     * Daftar kategori buku
     */
    public function indexCategories()
    {
        $categories = BookCategory::orderBy('name')->get([
            'id_book_category', 'name', 'slug', 'description', 'created_at', 'updated_at',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar kategori buku berhasil diambil.',
            'data'    => $categories,
        ]);
    }

    // =========================================================================
    // PEMESANAN BUKU
    // =========================================================================

    /**
     * POST /api/library/books/{id}/order
     * Pesan buku (cek stok dahulu)
     */
    public function orderBook($id)
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            // Lock row buku terlebih dahulu untuk mencegah race condition
            // (2 user memesan buku yang stoknya hanya 1 secara bersamaan)
            $book = Book::lockForUpdate()->findOrFail($id);

            // Cek apakah buku aktif
            if ($book->status !== Book::STATUS_ACTIVE) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Buku ini sedang tidak aktif.',
                ], 422);
            }

            // Cek stok (di dalam lock agar atomic)
            if (!$book->isAvailable()) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Stok buku habis. Tidak dapat memesan.',
                ], 422);
            }

            // Cek apakah user sudah punya pesanan aktif untuk buku yang sama
            $existingOrder = BookOrder::where('id_user', $user->id_user_si)
                ->where('id_book', $book->id_book)
                ->whereIn('status', [BookOrder::STATUS_ORDERED, BookOrder::STATUS_BORROWED])
                ->first();

            if ($existingOrder) {
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Anda sudah memiliki pesanan aktif untuk buku ini.',
                ], 422);
            }

            // Buat pesanan
            $order = BookOrder::create([
                'id_user'    => $user->id_user_si,
                'id_book'    => $book->id_book,
                'status'     => BookOrder::STATUS_ORDERED,
                'ordered_at' => now(),
            ]);

            // Kurangi stok (atomic, sudah di-lock)
            $book->decrement('available_stock');

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Buku berhasil dipesan.',
                'data'    => $this->formatOrder($order->load(['book.category', 'user'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal memesan buku', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memesan buku.',
            ], 500);
        }
    }


    // =========================================================================
    // AKTIVITAS PERPUSTAKAAN
    // =========================================================================

    /**
     * GET /api/library/activities
     * Riwayat peminjaman user yang sedang login
     */
    public function indexActivities(Request $request)
    {
        $user = Auth::user();

        $query = BookOrder::with(['book.category'])
            ->where('id_user', $user->id_user_si)
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $orders = $query->get()->map(fn ($order) => $this->formatOrder($order));

        return response()->json([
            'status'  => 'success',
            'message' => 'Riwayat aktivitas perpustakaan berhasil diambil.',
            'data'    => $orders,
        ]);
    }

    /**
     * GET /api/library/activities/{id}
     * Detail aktivitas peminjaman
     */
    public function showActivity($id)
    {
        $user = Auth::user();

        $order = BookOrder::with(['book.category'])->findOrFail($id);

        // Pastikan milik user yang login
        if ($order->id_user !== $user->id_user_si) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki akses ke aktivitas ini.',
            ], 403);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail aktivitas berhasil diambil.',
            'data'    => $this->formatOrder($order),
        ]);
    }

    /**
     * PATCH /api/library/activities/{id}/cancel
     * Batalkan pesanan (hanya status ordered)
     */
    public function cancelOrder($id)
    {
        $user  = Auth::user();
        $order = BookOrder::findOrFail($id);

        // Pastikan milik user yang login
        if ($order->id_user !== $user->id_user_si) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki izin untuk membatalkan pesanan ini.',
            ], 403);
        }

        // Hanya pesanan dengan status ordered yang bisa dibatalkan
        if ($order->status !== BookOrder::STATUS_ORDERED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pesanan ini tidak dapat dibatalkan karena sudah diproses.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order->update(['status' => BookOrder::STATUS_CANCELLED]);

            // Kembalikan stok
            $order->book->increment('available_stock');

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan berhasil dibatalkan.',
                'data'    => $this->formatOrder($order->fresh()->load(['book.category'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal membatalkan pesanan', ['error' => $e->getMessage()]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membatalkan pesanan.',
            ], 500);
        }
    }

    // =========================================================================
    // USULAN BUKU
    // =========================================================================

    /**
     * GET /api/library/suggestions
     * Daftar usulan buku milik user yang sedang login
     */
    public function indexSuggestions(Request $request)
    {
        $user = Auth::user();

        $query = BookSuggestion::where('id_user', $user->id_user_si)
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $suggestions = $query->get()->map(fn ($s) => $this->formatSuggestion($s));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar usulan buku berhasil diambil.',
            'data'    => $suggestions,
        ]);
    }

    /**
     * POST /api/library/suggestions
     * Kirim usulan buku baru
     */
    public function storeSuggestion(Request $request)
    {
        $validated = $request->validate([
            'title'  => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'reason' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        $suggestion = BookSuggestion::create([
            'id_user' => $user->id_user_si,
            'title'   => $validated['title'],
            'author'  => $validated['author'],
            'reason'  => $validated['reason'],
            'status'  => BookSuggestion::STATUS_PENDING,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Usulan buku berhasil dikirim.',
            'data'    => $this->formatSuggestion($suggestion),
        ], 201);
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
}
