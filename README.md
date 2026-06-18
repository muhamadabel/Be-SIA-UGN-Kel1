# Be-SIA-UGN — Kelompok 1 (Standalone untuk Testing)

Backend (Laravel 12 + Sanctum + Spatie Permission + MySQL) berisi **modul Kelompok 1**
dari SIA UGN, dikemas **berdiri sendiri** (DB + auth + data seed sendiri) supaya bisa
dipakai/diuji terpisah dari repo bersama — mis. untuk keperluan mata kuliah lain.

> Repo ini hasil ekstrak dari repo bersama `SIA-UGN/Be-SIA-UGN`. Infrastruktur inti
> (auth, user, kelas, periode akademik, kampus, jadwal) + controller pendukung presensi/gaji
> **ikut disertakan** karena modul Kel-1 (khususnya Presensi & Gaji) membutuhkannya agar jalan.

---

## Modul Kelompok 1 (fokus repo ini)

| Modul | Controller | Endpoint utama |
|---|---|---|
| **Angka Kredit / BKD** | `Api/BkdController` | `/api/lecturer/bkd*`, `/api/manager/bkd/*` |
| **Kegiatan Mengajar** | `Api/KegiatanPengajarController` | `/api/lecturer/kegiatan-pengajar*`, `/api/manager/kegiatan-pengajar/*` |
| **Penelitian (Proposal)** | `Api/PenelitianProposalController` | `/api/lecturer/penelitian-proposal*`, `/api/manager/penelitian-proposal/*` |
| **Publikasi Ilmiah** | `Api/PenelitianIlmiahController` | `/api/lecturer/penelitian*`, `/api/manager/penelitian/*` |
| **Presensi GPS Dosen** | `PresensiDosenController` (+ `AttendanceController`, `RekapPresensiController`) | `/api/lecturer/attendance/check-in`, `/api/lecturer/attendance/*`, `/api/lecturer/schedules*` |
| **Gaji / Payroll Dosen** | `PayrollController`, `ManagerPayrollController`, `AttendancePayrollSyncController` | `/api/lecturer/payroll*`, `/api/manager/payroll/*` |
| **Agregasi Manager (Aktivitas Dosen)** | `Api/ManagerLecturerController` | `/api/manager/lecturers/{id}/{profile,aktivitas}` |

File milik Kel-1:
- Controllers: `app/Http/Controllers/Api/{Bkd,KegiatanPengajar,ManagerLecturer,PenelitianIlmiah,PenelitianProposal}Controller.php`, `app/Http/Controllers/PresensiDosenController.php`
- Models: `app/Models/{BebanKerjaDosen,BkdKegiatan,KegiatanPengajar,PenelitianIlmiah,PenelitianAuthor,PenelitianProposal,PengajuanKenaikanJabatan,PresensiDosen}.php`
- Migrations: tabel `beban_kerja_dosens`, `bkd_kegiatans`, `pengajuan_kenaikan_jabatans`, `penelitian_ilmiahs`, `penelitian_authors`, `kegiatan_pengajars`, `penelitian_proposals`, `presensi_dosen`, `campus_settings`, `schedules` + migrasi detail Kel-1 (di `database/migrations/`)
- Routes: blok `[KELOMPOK 1]` di `routes/api.php`

> Catatan: modul **Pengabdian Masyarakat** TIDAK termasuk — itu milik kelompok lain.

---

## Cara menjalankan

Prasyarat: PHP 8.3+, Composer, MySQL (mis. Laragon).

```bash
# 1. Dependency
composer install

# 2. Environment
cp .env.example .env
#   set di .env: DB_DATABASE=be_sia_kel1  (DB_USERNAME=root, DB_PASSWORD kosong utk Laragon)
php artisan key:generate

# 3. Database (buat DB-nya dulu)
#   mysql:  CREATE DATABASE be_sia_kel1;
php artisan migrate:fresh --seed

# 4. Storage link (untuk file upload)
php artisan storage:link

# 5. Jalankan
php artisan serve            # http://127.0.0.1:8000  (API: /api)
```

---

## Akun test (hasil seed)

| Role | Email | Password |
|---|---|---|
| Admin | admin@gmail.com | admin123 |
| Manager | manager@gmail.com | manager123 |
| Dosen | dosen@gmail.com | dosen123 |
| Mahasiswa | ahmad.rizki@gmail.com | student123 |

Login: `POST /api/auth/login` → token di `data.access_token`. Kirim header
`Authorization: Bearer <token>` untuk endpoint terproteksi.

---

## Data test presensi

- Kelas demo **"Pemrograman Web Lanjut"** (id_class=1) sudah punya **16 pertemuan mingguan** (Selasa).
- Lokasi kampus untuk validasi GPS presensi: **UGM** (-7.77127, 110.37754) & **UNY** (-7.77257, 110.39290), radius 500 m. (Tidak ada lokasi pribadi.)
- **Logic check-in presensi**: boleh dilakukan mulai tanggal pertemuan **hingga 7 hari setelahnya** (tenggat seminggu), tidak harus tepat hari-H. Di luar rentang → ditolak.
  - Tes GPS tanpa pergi ke lokasi asli: mock lokasi di Chrome DevTools → Sensors → Location → isi koordinat UGM.

---

## Status flow tiap modul

- **Kegiatan Mengajar**: otomatis dari kelas yang diajar → Ajukan → Diajukan → Manager Setujui/Tolak/Revisi. AK = SKS × 0.5 (saat Disetujui).
- **Penelitian (Proposal)**: Pengajuan → Manager Setujui (isi AK → Aktif) → Selesai / Ditolak / Revisi.
- **Publikasi**: Draft → Diajukan → Disetujui / Ditolak / Revisi.
- **BKD/Angka Kredit**: input kegiatan → finalisasi (draft→diajukan) → Manager Setujui/Tolak. AK hanya dihitung saat **disetujui**.
- **Presensi**: check-in GPS (window 7 hari) → rekap → sinkron ke Gaji.
- **Gaji**: dihitung dari rekap kehadiran (gaji pokok + tunjangan − potongan).
