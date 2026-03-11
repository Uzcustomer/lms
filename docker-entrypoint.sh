#!/bin/sh
set -e

php artisan config:cache
php artisan migrate --force 2>/dev/null || true

# Deploy bo'lganda bir martalik eslatma yuborish (DB tayyor bo'lishini kutib)
(sleep 30 && php artisan teachers:send-lesson-opening-reminders >> /var/log/lesson-opening-reminders.log 2>&1) &

exec "$@"
