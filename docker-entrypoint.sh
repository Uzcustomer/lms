#!/bin/sh
set -e

php artisan config:cache

# Deploy bo'lganda bir martalik eslatma yuborish (DB tayyor bo'lishini kutib)
(sleep 30 && php artisan teachers:send-lesson-opening-reminders >> /var/log/lesson-opening-reminders.log 2>&1) &

exec "$@"
