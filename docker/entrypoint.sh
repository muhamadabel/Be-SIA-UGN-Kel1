#!/bin/sh
# Entrypoint container CapRover — dijalankan tiap kali container start/deploy.
set -e

echo "[entrypoint] Membersihkan cache config lama..."
php artisan config:clear || true

echo "[entrypoint] Menjalankan migrasi database (--force)..."
php artisan migrate --force || true

# Seed data awal HANYA bila env RUN_SEED=true (set sekali saat deploy pertama, lalu hapus).
if [ "$RUN_SEED" = "true" ]; then
  echo "[entrypoint] RUN_SEED=true -> menjalankan db:seed..."
  php artisan db:seed --force || true
fi

echo "[entrypoint] storage:link + cache config..."
php artisan storage:link || true
php artisan config:cache || true

echo "[entrypoint] Menjalankan Apache (foreground)..."
exec apache2-foreground
