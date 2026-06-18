# Deploy ke CapRover (Auto-Deploy dari GitHub)

Repo ini sudah siap deploy ke CapRover. File yang dipakai:
- `captain-definition` → menunjuk ke `Dockerfile`
- `Dockerfile` → build Laravel (PHP 8.3 + Apache, docroot `/public`)
- `docker/entrypoint.sh` → otomatis `migrate` tiap deploy (+ `db:seed` bila `RUN_SEED=true`)

Yang di bawah ini dilakukan di **dashboard CapRover** kamu (sekali setup).

---

## 1) Siapkan database MySQL

1. CapRover → **Apps → One-Click Apps/Databases** → cari **MariaDB** atau **MySQL** → deploy.
2. Catat saat setup:
   - Nama app, mis. `mysql-kel1` → host internal jadi **`srv-captain--mysql-kel1`**
   - **Root password** yang kamu isi
3. Buat database: lewat app DB itu (atau deploy **phpMyAdmin/Adminer** one-click) → `CREATE DATABASE be_sia_kel1;`

---

## 2) Buat App untuk Backend

1. CapRover → **Apps → Create A New App** → nama mis. `be-sia-kel1` → Create.
2. Buka app → tab **HTTP Settings**:
   - **Container HTTP Port** = `80`
   - (opsional) Enable HTTPS + Force HTTPS
3. Tab **App Configs → Environmental Variables**, isi:

   | Key | Value |
   |---|---|
   | `APP_NAME` | `SIA UGN Kel-1 BE` |
   | `APP_ENV` | `production` |
   | `APP_KEY` | `base64:....` (lihat catatan di bawah) |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://be-sia-kel1.<domain-caprover-kamu>` |
   | `LOG_CHANNEL` | `stderr` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_HOST` | `srv-captain--mysql-kel1` |
   | `DB_PORT` | `3306` |
   | `DB_DATABASE` | `be_sia_kel1` |
   | `DB_USERNAME` | `root` |
   | `DB_PASSWORD` | `<root password MySQL tadi>` |
   | `SESSION_DRIVER` | `database` |
   | `CACHE_STORE` | `database` |
   | `QUEUE_CONNECTION` | `sync` |
   | `RUN_SEED` | `true` *(hanya untuk deploy PERTAMA, lalu hapus/ubah `false`)* |

   > **APP_KEY**: generate dengan `php artisan key:generate --show` di lokal, atau pakai value yang sudah disiapkan (lihat chat). Wajib diisi.

4. Klik **Save & Update**.

---

## 3) Hubungkan GitHub (auto-deploy tiap push)

1. Buka app `be-sia-kel1` → tab **Deployment**.
2. Bagian **Method 3: Deploy from Github/Bitbucket/Gitlab**:
   - **Repository**: `github.com/muhamadabel/Be-SIA-UGN-Kel1`
   - **Branch**: `main`
   - **Username**: `muhamadabel`
   - **Password**: GitHub **Personal Access Token** (Settings → Developer settings → Tokens, scope `repo`)
   - Klik **Save & Update**.
3. CapRover menampilkan sebuah **Webhook URL**. **Copy** URL itu.
4. Buka GitHub repo → **Settings → Webhooks → Add webhook**:
   - **Payload URL**: tempel webhook CapRover
   - **Content type**: `application/json`
   - **Events**: *Just the push event*
   - **Add webhook**.

Sekarang tiap `git push` ke `main` → GitHub memanggil webhook → CapRover pull + build `Dockerfile` + deploy otomatis.

---

## 4) Deploy pertama & seed

- Trigger deploy pertama: tab **Deployment → Force Build**, atau push commit apa pun.
- Karena `RUN_SEED=true`, entrypoint akan menjalankan `migrate` + `db:seed` → tabel + data test (akun, kelas, kampus) terbuat.
- **Setelah berhasil**, balik ke Environmental Variables → **hapus `RUN_SEED`** (atau set `false`) supaya tidak re-seed tiap deploy. Lalu Save & Update.

Akun test: `dosen@gmail.com/dosen123`, `manager@gmail.com/manager123` (lihat `README.md`).

---

## 5) Cek & troubleshoot

- Log build/runtime: app → **Deployment → Build Logs** & **App Logs**.
- API: `https://be-sia-kel1.<domain>/api/auth/login` (POST email+password).
- Kalau error koneksi DB: pastikan `DB_HOST` = `srv-captain--<nama-app-mysql>` dan password benar.
- Kalau 500 + `APP_KEY` error: pastikan env `APP_KEY` terisi `base64:...`.
- File upload hilang tiap deploy? tambah **Persistent Directory**: Path in App = `/var/www/html/storage/app/public`.
