#!/bin/sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
LISTEN_PORT_VALUE="${LISTEN_PORT:-${FC_SERVER_PORT:-9000}}"
NGINX_RENDERED="/tmp/talk-nginx.conf"

mkdir -p /tmp/nginx-client-body /tmp
cp "$ROOT_DIR/nginx.conf" "$NGINX_RENDERED"

escape_sed_replacement() {
    printf '%s' "$1" | sed 's/[|&]/\\&/g'
}

replace_placeholder() {
    placeholder="$1"
    value="$2"
    safe_value="$(escape_sed_replacement "$value")"
    sed -i "s|$placeholder|$safe_value|g" "$NGINX_RENDERED"
}

value_b64() {
    name="$1"
    eval "value=\${$name:-}"
    printf '%s' "$value" | base64 | tr -d '\n'
}

replace_placeholder "__APP_ROOT__" "$ROOT_DIR"
replace_placeholder "__LISTEN_PORT__" "$LISTEN_PORT_VALUE"

for name in \
    DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD FRONTEND_URL ALLOW_NO_ORIGIN_REQUESTS TZ GLOBAL_SALT_3 \
    PBKDF2_ITERATIONS MAX_FILE_SUM_BYTES MAX_CIPHERTEXT_BYTES CODE_LENGTH DEFAULT_EXPIRE_DAYS MAX_EXPIRE_DAYS \
    CREATE_LIMIT_PER_HOUR CHECK_LIMIT_PER_HOUR
    do
        replace_placeholder "__${name}_B64__" "$(value_b64 "$name")"
    done

php-fpm7.4 -y "$ROOT_DIR/php-fpm.conf" -c "$ROOT_DIR/php.ini"
exec nginx -c "$NGINX_RENDERED" -g 'daemon off;'
