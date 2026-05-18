#!/bin/sh
# Fix ownership of writable directories so PHP-FPM (www-data) can write to them.
# Required because volume-mounted directories on the host may have a different
# uid than www-data inside the Alpine-based PHP image (uid 82 vs uid 33).
set -e

WRITABLE_DIRS="
/var/www/html/storage/framework/cache
/var/www/html/storage/framework/sessions
/var/www/html/storage/framework/views
/var/www/html/storage/logs
/var/www/html/bootstrap/cache
"

for dir in $WRITABLE_DIRS; do
    mkdir -p "$dir"
    chown -R www-data:www-data "$dir"
done

exec "$@"
