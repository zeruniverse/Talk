# Talk

Transfer sensitive messages and attachments safely.

This package is prepared for this deployment model:

- `frontend/` -> Cloudflare Pages static site
- `backend/` + `nginx.conf` + `php-fpm.conf` + `php.ini` + `start.sh` -> Aliyun Function Compute custom runtime / web function
- `initial.sql` -> MySQL schema

The frontend does not load JavaScript from any external source. It only loads these local files:

```text
/assets/config.js
/assets/crypto.js
/assets/app.js
```

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

## Security and storage model

Talk encrypts everything in the browser before upload. The backend stores only one opaque encrypted blob in `talk_messages.ciphertext`, which is a `MEDIUMBLOB`.

For each message:

1. The browser generates a random salt and derives key material from the passphrase using PBKDF2-SHA256.
2. The derived material is split into:
   - an AES-GCM key for browser-side encryption and decryption;
   - an access token that the backend verifies by HMAC with `GLOBAL_SALT_3`.
3. Each attachment is AES-GCM encrypted separately.
4. The browser builds this JSON:

```json
{
  "message": "xxxxx",
  "files": [
    { "name": "xxx.ext", "bytelen": "12345" }
  ]
}
```

`bytelen` is the encrypted byte length of that attachment block.

5. The JSON is AES-GCM encrypted.
6. The browser assembles:

```text
3-byte encrypted JSON length | encrypted JSON block | encrypted file block 1 | encrypted file block 2 | ...
```

7. The whole assembled block is AES-GCM encrypted again and uploaded as `ciphertext`.

When opening a message, the frontend first verifies the key with `check.php`. If the check passes, it uses XHR `POST` to fetch the complete encrypted blob from `open.php`. By default, the backend deletes the server-side copy during this successful open operation. If `Allow multiple views` was selected during creation, the server-side copy is kept until expiration. The browser then decrypts the outer block, decrypts the JSON block, displays `message` as raw HTML, and creates download links for each attachment. Attachment files are decrypted only when the user clicks a download link.

## Limits

Defaults:

- Total original attachment size: 15 MiB (`MAX_FILE_SUM_BYTES`)
- Final encrypted blob size: at most 16 MiB minus 1 byte (`MAX_CIPHERTEXT_BYTES`, MySQL `MEDIUMBLOB` limit is 16,777,215 bytes)
- PHP / Nginx body upload size: 20 MiB

## Database

Create a MySQL database and import `initial.sql`.

```bash
mysql -u USER -p DATABASE < initial.sql
```

The main table uses `MEDIUMBLOB` for `ciphertext`:

```sql
`ciphertext` mediumblob NOT NULL
```

## Frontend deployment on Cloudflare Pages

Deploy the `frontend/` directory.

Before deployment, edit `frontend/assets/config.js`:

```js
window.TALK_CONFIG = {
  API_BASE: 'https://api.example.com/api',
  FRONTEND_BASE: 'https://talk.example.com',
  DEFAULT_PASSWORD: 'change-this-default-passphrase',
  PBKDF2_ITERATIONS: 210000,
  MAX_FILE_SUM_BYTES: 15 * 1024 * 1024,
  MAX_UPLOAD_BYTES: 16 * 1024 * 1024 - 1
};
```

Also edit `frontend/_headers` and replace this placeholder with your backend domain:

```text
connect-src https://api.example.com
```

Cloudflare Pages will use `frontend/_redirects` so short links like `https://talk.example.com/AbC123xy` open the SPA.

## Backend deployment on Aliyun Function Compute

Deploy these files and directories as the backend code package root:

```text
backend/
nginx.conf
php-fpm.conf
php.ini
start.sh
```

Use `start.sh` as the startup command. The default listening port is `9000`. `start.sh` also accepts `LISTEN_PORT` or `FC_SERVER_PORT`.

Set these environment variables in Aliyun FC:

```text
DB_HOST=your-mysql-host
DB_PORT=3306
DB_NAME=your-database
DB_USER=your-database-user
DB_PASSWORD=your-database-password
FRONTEND_URL=https://talk.example.com/
GLOBAL_SALT_3=replace-with-a-long-random-secret
```

Generate `GLOBAL_SALT_3` with:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Optional environment variables:

```text
ALLOW_NO_ORIGIN_REQUESTS=false
TZ=America/Los_Angeles
PBKDF2_ITERATIONS=210000
MAX_FILE_SUM_BYTES=15728640
MAX_CIPHERTEXT_BYTES=16777215
CODE_LENGTH=8
DEFAULT_EXPIRE_DAYS=6
MAX_EXPIRE_DAYS=30
CREATE_LIMIT_PER_HOUR=120
CHECK_LIMIT_PER_HOUR=600
```

## Editable config fallback

`backend/function/config.php` reads environment variables first. If you do not want to use env vars, you can directly edit fallback values in `config.php`, for example:

```php
$DB_HOST = talk_config_value('DB_HOST', '127.0.0.1');
$DB_NAME = talk_config_value('DB_NAME', 'talk');
$DB_USER = talk_config_value('DB_USER', 'talk_user');
$DB_PASSWORD = talk_config_value('DB_PASSWORD', 'secret');
$FRONTEND_URL = talk_config_value('FRONTEND_URL', 'https://talk.example.com/');
```

The Nginx startup script injects environment variables into PHP through `fastcgi_param`. Values are Base64 encoded before insertion into the generated Nginx config so database passwords containing `$`, quotes, backslashes, or slashes do not break the config.

## API endpoints

```text
GET  /api/health.php
POST /api/create.php      multipart/form-data with ciphertext blob
GET  /api/meta.php?code=CODE
POST /api/check.php       JSON: {"code":"...","token":"..."}
POST /api/open.php        JSON: {"code":"...","token":"..."}; returns application/octet-stream
```

## Notes

- The passphrase is never sent to the backend. Leaving the passphrase field blank uses `DEFAULT_PASSWORD` from `frontend/assets/config.js`.
- The backend cannot decrypt messages or attachments.
- Messages are one-time read by default. If `Allow multiple views` is selected, a successful `open.php` call keeps the stored blob until expiration.
- Message output is intentionally rendered as raw HTML because this project explicitly preserves HTML tags in messages. Only share links with people you trust.
