# Pakai Supabase (PostgreSQL) sebagai Database

App ini awalnya MySQL, tapi sudah dibuat **driver-aware** sehingga bisa jalan di
**PostgreSQL/Supabase**. Migration yang sebelumnya MySQL-only (`ALTER ... MODIFY ... ENUM`)
kini otomatis memakai `CHECK constraint` saat driver `pgsql`.

> Driver `pdo_pgsql` sudah ada di `Dockerfile`. Untuk lokal, pastikan ekstensi
> `pdo_pgsql` aktif di PHP-mu (`php -m | grep pgsql`).

---

## 1) Ambil kredensial koneksi dari Supabase

1. Buat project di https://supabase.com → tunggu provisioning.
2. **Project Settings → Database → Connection string** → pilih tab **Session pooler**.
   - Gunakan **Session pooler (port 5432)** — kompatibel Laravel (mendukung prepared statements).
   - **JANGAN** pakai Transaction pooler (port 6543): Laravel akan error karena pooler itu
     tidak mendukung prepared statements.
3. Catat: `host`, `port` (5432), `database` (biasanya `postgres`), `user`
   (`postgres.<project-ref>`), dan **database password** (yang kamu set saat buat project).

---

## 2) Set environment

Di `.env` (lokal) atau **Environmental Variables** (CapRover):

```
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<project-ref>
DB_PASSWORD=<database-password>
DB_SSLMODE=require
```

> `DB_SSLMODE=require` penting — Supabase memaksa SSL.

---

## 3) Migrasi & seed

```bash
php artisan migrate:fresh --seed --force
```

Di CapRover, entrypoint (`docker/entrypoint.sh`) menjalankan `migrate --force` otomatis
tiap deploy. Untuk seed pertama, set env `RUN_SEED=true` (lalu hapus setelah berhasil).

> Jika ingin migrate manual ke Supabase dari lokal: set env di atas lalu jalankan
> `php artisan migrate:fresh --seed`.

---

## 4) Catatan & troubleshooting

- **Prepared statement error / "unnamed prepared statement"** → kamu memakai port **6543**
  (transaction pooler). Ganti ke **5432** (session pooler).
- **SSL required** → tambahkan `DB_SSLMODE=require`.
- **Tabel sudah ada / enum check** → jalankan `migrate:fresh` (drop & buat ulang) untuk DB baru.
- **Koneksi lambat / connection limit** → session pooler sudah menangani pooling; untuk free tier
  batasi worker/queue.
- Validasi nilai status (mis. `Disetujui`, `Diajukan`) tetap dijaga di level aplikasi +
  `CHECK constraint` Postgres, jadi data tetap konsisten.

---

## 5) Mana yang dipakai?

- **MySQL** (default `.env.example` Opsi A) → paling mulus, app dibangun untuk ini.
- **Supabase/PostgreSQL** (Opsi B) → didukung penuh setelah penyesuaian driver-aware ini.

Tinggal ganti blok `DB_*` di env; kode aplikasi tidak perlu diubah.
