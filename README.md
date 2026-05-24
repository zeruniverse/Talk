# Talk

Talk transfers sensitive messages through a link and a shared passphrase. Message content is encrypted and decrypted in the browser. The backend stores ciphertext, public encryption metadata, and a server-HMACed access token; it does not store plaintext or the passphrase.

This package is structured for:

- `frontend/` on Cloudflare Pages
- `backend/` on Aliyun Function Compute custom runtime behind Nginx + PHP-FPM
- MySQL-compatible database initialized with `initial.sql`

## Project structure

```text
initial.sql
README.md
LICENSE
frontend/
backend/
nginx.conf
php-fpm.conf
php.ini
start.sh
```

## Security notes

- The frontend does not load JavaScript from any third-party source.
- `frontend/index.html` loads only:
  - `/assets/config.js`
  - `/assets/crypto.js`
  - `/assets/app.js`
- Message encryption uses AES-GCM through the Web Crypto API.
- The passphrase is processed locally with PBKDF2-SHA256.
- Each message has its own random salt and IV.
- The backend stores only an HMAC of the derived access token.
- One-time messages are deleted inside a database transaction after successful access-token verification.

## Database setup

Create a database and import:

```bash
mysql -u your_user -p your_database < initial.sql
```

The schema creates:

- `talk_messages` for encrypted messages
- `talk_rate_limits` for application-layer API rate limiting

## Frontend deployment on Cloudflare Pages

Deploy the `frontend/` directory as a static site.

Before deployment, edit `frontend/assets/config.js`:

```js
window.TALK_CONFIG = {
  API_BASE: 'https://api.example.com/api',
  FRONTEND_URL: 'https://talk.example.com/',
  PBKDF2_ITERATIONS: 310000,
  DEFAULT_EXPIRE_DAYS: 5
};
```

Then edit `frontend/_headers` and replace:

```text
connect-src 'self' https://api.example.com;
```

with your real backend API origin.

Cloudflare Pages uses `frontend/_redirects` so links like `https://talk.example.com/AbCdEf123` are served by `index.html`.

## Backend deployment on Aliyun FC

Deploy the project root or at least these backend files to the FC code package:

```text
backend/
nginx.conf
php-fpm.conf
php.ini
start.sh
```

Use `start.sh` as the custom runtime startup command.

Nginx listens on `LISTEN_PORT`, then `FC_SERVER_PORT`, then `9000` by default. PHP-FPM listens locally on `127.0.0.1:9001`.

### Required environment variables

Set these in Aliyun FC:

```text
DB_HOST=your-rds-host
DB_PORT=3306
DB_NAME=your_database
DB_USER=your_user
DB_PASSWORD=your_password
FRONTEND_URL=https://talk.example.com/
GLOBAL_SALT_3=replace-with-a-long-random-secret
```

Generate `GLOBAL_SALT_3` with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Do not change `GLOBAL_SALT_3` after real messages have been created. Changing it makes existing links fail access-token verification.

### Optional environment variables

```text
ALLOW_NO_ORIGIN_REQUESTS=false
TZ=America/Los_Angeles
MAX_CIPHERTEXT_BYTES=1048576
DEFAULT_EXPIRE_DAYS=5
MAX_EXPIRE_DAYS=30
MIN_PBKDF2_ITERATIONS=100000
MAX_PBKDF2_ITERATIONS=2000000
RATE_LIMIT_WINDOW=300
RATE_LIMIT_CREATE=20
RATE_LIMIT_META=120
RATE_LIMIT_OPEN=60
RATE_LIMIT_OPEN_CODE=10
CLEANUP_LIMIT=1000
```

### Editing config.php directly instead of env vars

`backend/function/config.php` reads environment variables first. If you do not want to use environment variables, edit the fallback values directly in that file:

```php
$DB_HOST = talk_env('DB_HOST', '');
$DB_PORT = talk_env_int('DB_PORT', 3306);
$DB_NAME = talk_env('DB_NAME', '');
$DB_USER = talk_env('DB_USER', '');
$DB_PASSWORD = talk_env('DB_PASSWORD', '');
$FRONTEND_URL = talk_env('FRONTEND_URL', 'https://abc.github.io/talk/');
$GLOBAL_SALT_3 = talk_env('GLOBAL_SALT_3', '*&Kjnskjnaucibiqb9298hv9sHIUWNiukJNIusfbic897*(^)');
```

For example:

```php
$DB_HOST = talk_env('DB_HOST', 'rm-xxxx.mysql.rds.aliyuncs.com');
$DB_NAME = talk_env('DB_NAME', 'talk');
$DB_USER = talk_env('DB_USER', 'talk_user');
$DB_PASSWORD = talk_env('DB_PASSWORD', 'your-password');
$FRONTEND_URL = talk_env('FRONTEND_URL', 'https://talk.example.com/');
$GLOBAL_SALT_3 = talk_env('GLOBAL_SALT_3', 'your-long-random-secret');
```

## API endpoints

Backend API paths:

```text
GET  /api/health.php
POST /api/create.php
GET  /api/meta.php?code=...
POST /api/open.php
```

Only `FRONTEND_URL`'s origin is allowed by CORS. Set `ALLOW_NO_ORIGIN_REQUESTS=true` only for temporary CLI testing.

## Local smoke test notes

The frontend needs a secure context for Web Crypto in modern browsers. Use HTTPS in production. For local browser tests, `localhost` is usually treated as a secure context.

## License

This project keeps the original Talk license in `LICENSE`.
