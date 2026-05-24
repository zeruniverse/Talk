#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
LISTEN_PORT_VALUE="${LISTEN_PORT:-${FC_SERVER_PORT:-9000}}"
RENDERED_NGINX="/tmp/talk-nginx.conf"

b64_env() {
    name="$1"
    eval "value=\${$name:-}"
    if command -v base64 >/dev/null 2>&1; then
        printf '%s' "$value" | base64 | tr -d '\n'
    else
        php -r 'echo base64_encode(stream_get_contents(STDIN));'
    fi
}

replace_placeholder() {
    file="$1"
    placeholder="$2"
    value="$3"
    escaped=$(printf '%s' "$value" | sed 's/[\\&|]/\\&/g')
    sed -i "s|$placeholder|$escaped|g" "$file"
}

mkdir -p /tmp/client_body /tmp/proxy_temp /tmp/fastcgi_temp /tmp/uwsgi_temp /tmp/scgi_temp
cp "$ROOT_DIR/nginx.conf" "$RENDERED_NGINX"

replace_placeholder "$RENDERED_NGINX" "__LISTEN_PORT__" "$LISTEN_PORT_VALUE"
replace_placeholder "$RENDERED_NGINX" "__ROOT_DIR__" "$ROOT_DIR"

for name in \
    DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD FRONTEND_URL ALLOW_NO_ORIGIN_REQUESTS TZ GLOBAL_SALT_3 \
    MAX_CIPHERTEXT_BYTES DEFAULT_EXPIRE_DAYS MAX_EXPIRE_DAYS MIN_PBKDF2_ITERATIONS MAX_PBKDF2_ITERATIONS \
    RATE_LIMIT_WINDOW RATE_LIMIT_CREATE RATE_LIMIT_META RATE_LIMIT_OPEN RATE_LIMIT_OPEN_CODE CLEANUP_LIMIT
 do
    replace_placeholder "$RENDERED_NGINX" "__${name}_B64__" "$(b64_env "$name")"
 done

php-fpm -y "$ROOT_DIR/php-fpm.conf" -c "$ROOT_DIR/php.ini"
exec nginx -c "$RENDERED_NGINX" -g 'daemon off;'
