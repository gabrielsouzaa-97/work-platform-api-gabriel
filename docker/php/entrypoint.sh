#!/bin/sh
# Fix ownership of writable directories so PHP-FPM (www-data) can write to them.
# Required because volume-mounted directories on the host may have a different
# uid than www-data inside the Alpine-based PHP image (uid 82 vs uid 33).
set -e

configure_xdebug() {
    if ! php -m 2>/dev/null | grep -qi '^xdebug$'; then
        return
    fi

    cat > /usr/local/etc/php/conf.d/99-xdebug-runtime.ini <<EOF
xdebug.mode=${XDEBUG_MODE:-off}
xdebug.client_host=${XDEBUG_CLIENT_HOST:-host.docker.internal}
xdebug.client_port=${XDEBUG_CLIENT_PORT:-9003}
xdebug.start_with_request=${XDEBUG_START_WITH_REQUEST:-trigger}
xdebug.idekey=${XDEBUG_IDEKEY:-VSCODE}
EOF
}

configure_xdebug

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
