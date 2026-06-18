<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenelitianIlmiah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenelitianIlmiahController extends Controller
{
    /**
     * Menyimpan data penelitian ilmiah baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'jenis_output' => 'required|in:Jurnal Nasional,Jurnal Internasional,Prosiding,Buku,Paten',
            'tahun_terbit' => 'required|digits:4',
            'nama_publikasi' => 'required|string',
            'volume' => 'nullable|string',
            'nomor' => 'nullable|string',
            'halaman' => 'nullable|string',
            'penerbit' => 'nullable|string',
            'doi_url' => 'nullable|url',
            'status_akreditasi' => 'nullable|string',
            'file_artikel' => 'nullable|file|mimes:pdf|max:5120', // 5MB
            'authors' => 'required|array',
            'authors.*.id_user_si' => 'required|exists:users_si,id_user_si',
            'authors.*.peran' => 'required|in:Penulis Utama,Anggota',
            'authors.*.urutan' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->except('authors', 'file_artikel');
            
            if ($request->hasFile('file_artikel')) {
                $data['file_artikel'] = $request->file('file_artikel')->store('penelitian', 'public');
            }

            // Status otomatis 'Draft' sudah di-handle oleh default value di migrasi
            $penelitian = PenelitianIlmiah::create($data);

            $pivotData = [];
            foreach ($request->authors as $author) {
                $pivotData[$author['id_user_si']] = [
                    'peran' => $author['peran'],
                    'urutan' => $author['urutan']
                ];
            }

            $penelitian->authors()->attach($pivotData);

            DB::commit();

            // Load relasi authors untuk response
            $penelitian->load('authors');

            return response()->json([
                'status' => 'success',
                'message' => 'Data penelitian berhasil disimpan.',
                'data' => $penelitian
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Sebaiknya log error di sini
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar penelitian ilmiah milik dosen yang login.
     */
    public function index(Request $request)
    {
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        if (!$id_user_si) {
            return response()->json(['status' => 'error', 'message' => 'ID Dosen tidak ditemukan.'], 400);
        }

        $penelitian = PenelitianIlmiah::whereHas('authors', function ($query) use ($id_user_si) {
            $query->where('users_si.id_user_si', $id_user_si);
        })
        ->with('authors') // Eager load relasi authors
        ->latest()
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $penelitian
        ]);
    }

    /**
     * [MANAGER] Menampilkan daftar penelitian yang memerlukan validasi.
     */
    public function getPenelitianManager(Request $request)
    {
        $penelitian = PenelitianIlmiah::where('status_validasi', 'Diajukan')
            ->with('authors') // Eager load relasi authors
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $penelitian
        ]);
    }

    /**
     * [MANAGER] Memvalidasi status penelitian.
     */
    public function validasiPenelitian(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_validasi'  => 'required|in:Disetujui,Ditolak,Revisi',
            'catatan_validasi' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $penelitian = PenelitianIlmiah::find($id);

        if (!$penelitian) {
            return response()->json(['status' => 'error', 'message' => 'Data penelitian tidak ditemukan.'], 404);
        }

        $penelitian->status_validasi = $request->status_validasi;
        $penelitian->catatan_validasi = $request->catatan_validasi;
        $penelitian->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status penelitian berhasil diperbarui.',
            'data' => $penelitian
        ]);
    }

    /**
     * [DOSEN] Edit penelitian miliknya — hanya saat status Draft atau Revisi.
     * POST /api/lecturer/penelitian/{id}/update
     */
    public function update(Request $request, $id)
    {
        $penelitian = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($penelitian->status_validasi, ['Draft', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Penelitian hanya bisa diedit saat status "Belum Diajukan" atau "Perlu Revisi".',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'jenis_output' => 'required|in:Jurnal Nasional,Jurnal Internasional,Prosiding,Buku,Paten',
            'tahun_terbit' => 'required|digits:4',
            'nama_publikasi' => 'required|string',
            'volume' => 'nullable|string',
            'nomor' => 'nullable|string',
            'halaman' => 'nullable|string',
            'penerbit' => 'nullable|string',
            'doi_url' => 'nullable|url',
            'status_akreditasi' => 'nullable|string',
            'file_artikel' => 'nullable|file|mimes:pdf|max:5120',
            'authors' => 'required|array',
            'authors.*.id_user_si' => 'required|exists:users_si,id_user_si',
            'authors.*.peran' => 'required|in:Penulis Utama,Anggota',
            'authors.*.urutan' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Hanya field yang boleh diedit dosen (status_validasi TIDAK termasuk).
            $penelitian->fill($request->only([
                'judul', 'jenis_output', 'nama_publikasi', 'tahun_terbit',
                'volume', 'nomor', 'halaman', 'penerbit', 'doi_url', 'status_akreditasi',
            ]));

            if ($request->hasFile('file_artikel')) {
                $penelitian->file_artikel = $request->file('file_artikel')->store('penelitian', 'public');
            }

            $penelitian->save();

            $pivotData = [];
            foreach ($request->authors as $author) {
                $pivotData[$author['id_user_si']] = [
                    'peran' => $author['peran'],
                    'urutan' => $author['urutan'],
                ];
            }
            $penelitian->authors()->sync($pivotData);

            DB::commit();
            $penelitian->load('authors');

            return response()->json([
                'status' => 'success',
                'message' => 'Data penelitian berhasil diperbarui.',
                'data' => $penelitian,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * [DOSEN] Ajukan penelitian ke manager: Draft/Revisi -> Diajukan.
     * POST /api/lecturer/penelitian/{id}/ajukan
     */
    public function ajukan(Request $request, $id)
    {
        $penelitian = $this->findOwned($request, $id, $error);
        if ($error) return $error;

        if (!in_array($penelitian->status_validasi, ['Draft', 'Revisi'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya penelitian berstatus "Belum Diajukan" atau "Perlu Revisi" yang bisa diajukan.',
            ], 422);
        }

        $penelitian->status_validasi = 'Diajukan';
        $penelitian->catatan_validasi = null; // reset catatan lama saat diajukan ulang
        $penelitian->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Penelitian berhasil diajukan dan menunggu validasi manager.',
            'data' => $penelitian,
        ]);
    }

    /**
     * Helper: ambil penelitian milik dosen login (dosen harus salah satu author).
     * Set $error (JsonResponse) jika gagal.
     */
    private function findOwned(Request $request, $id, &$error)
    {
        $error = null;
        $id_user_si = $request->id_user_si ?? $request->user()?->id_user_si;

        $penelitian = PenelitianIlmiah::with('authors')->find($id);
        if (!$penelitian) {
            $error = response()->json(['status' => 'error', 'message' => 'Data penelitian tidak ditemukan.'], 404);
            return null;
        }

        $isAuthor = $penelitian->authors->contains(fn ($a) => (int) $a->id_user_si === (int) $id_user_si);
        if (!$isAuthor) {
            $error = response()->json(['status' => 'error', 'message' => 'Bukan penelitian milik Anda.'], 403);
            return null;
        }

        return $penelitian;
    }
}
