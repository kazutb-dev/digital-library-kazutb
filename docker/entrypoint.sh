#!/bin/bash
set -Eeuo pipefail

# Ensure required Laravel runtime directories exist and are writable by php-fpm.
# This matters in live-sync mode because the repo is bind-mounted from the host,
# so `bootstrap/cache` and `storage` may otherwise inherit host ownership.
mkdir -p bootstrap/cache storage/framework/views storage/framework/sessions storage/framework/cache storage/logs storage/app/backups/admin
chown -R www-data:www-data storage bootstrap bootstrap/cache config resources public
chmod -R a+rX app config resources public
chmod 755 bootstrap
chmod 755 config resources
chmod 755 storage storage/framework storage/logs
chmod -R ug+rwX storage bootstrap/cache
chmod 770 storage/app/backups/admin
# Backup automation runs as www-data and restore operators use the www-data
# group. Preserve group-read while preventing the recursive runtime-directory
# setup above from making database artifacts group-writable again.
for backup_directory in storage/app/backups storage/backups/prod; do
  if [ -d "${backup_directory}" ]; then
    find "${backup_directory}" -type f \
      \( -name '*.dump' -o -name '*.dump.toc' -o -name '*.dump.sha256' -o -name '*.dump.verify.txt' \) \
      -exec chmod 640 {} +
  fi
done
chmod 644 bootstrap/app.php

# Remove stale bootstrap cache manifests that may reference dev-only packages
# (e.g. laravel/pail) not installed in production image.
rm -f bootstrap/cache/*.php

# Public uploads (for example validated resource logos) live on their own
# persistent volume. The link exposes only storage/app/public; private
# repository, contract and report files remain outside the web root.
php artisan storage:link --force --no-interaction
test -L public/storage
test "$(readlink -f public/storage)" = "$(readlink -f storage/app/public)"

# In production-style mode, remove any stale Vite hot-file so Blade uses the
# compiled assets in `public/build`. In live-sync mode we keep it, because the
# frontend-dev service uses `public/hot` to enable real-time JS/CSS updates.
if [ "${APP_LIVE_SYNC:-false}" != "true" ]; then
  rm -f public/hot
fi

# A normal application start must never mutate the database. Migrations are a
# separate, explicitly approved deployment operation. Refuse to serve when the
# checked-out code and the selected database schema are not already compatible.
echo "[entrypoint] Checking runtime environment and schema compatibility (read-only)..."
php scripts/check-runtime-schema.php

# Clear or warm caches depending on runtime mode
if [ "${APP_LIVE_SYNC:-false}" = "true" ]; then
  echo "[entrypoint] Live sync mode enabled; clearing Laravel caches so route/view/code edits appear immediately..."
  php artisan optimize:clear
else
  echo "[entrypoint] Warming caches..."
  php artisan config:cache
  php artisan route:cache
  # A Blade compilation error would otherwise surface later as a reader-facing
  # HTTP 500.  Production startup is intentionally fail-closed here so a bad
  # release never begins accepting traffic.
  php artisan view:cache
fi

echo "[entrypoint] Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
